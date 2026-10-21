#!/usr/bin/env bash
# Build the flat stable branches of mod_contentdesigner from a tag: the free plugin without the paid element and addon
# submodules, so that sites deploying from git get no empty folders (which Moodle reports as broken plugins). Called by
# .github/workflows/stable-branches.yml.
#
# Environment:
#   SOURCE_DIR   mod_contentdesigner checkout of the tag, without submodules (required)
#   SOURCE_REF   the tag (required)
#   BRANCHES     space-separated stable branches, e.g. "MOODLE_405_STABLE MOODLE_503_STABLE" (required)
#   TARGET_URL   git URL used to clone and push this repository (required)
#   ACTOR        name for the commits

set -euo pipefail

: "${SOURCE_DIR:?}" "${SOURCE_REF:?}" "${BRANCHES:?}" "${TARGET_URL:?}"
ACTOR="${ACTOR:-stable-branches}"

fail() {
    echo "::error::$*"
    exit 1
}

grep -q "'mod_contentdesigner'" "$SOURCE_DIR/version.php" || fail "${SOURCE_DIR} is not a mod_contentdesigner checkout."
for br in $BRANCHES; do
    [[ "$br" =~ ^MOODLE_[0-9]+_STABLE$ ]] || fail "Invalid stable branch name: ${br}"
done
SHORT_SHA="$(git -C "$SOURCE_DIR" rev-parse --short=12 HEAD)"
mapfile -t SUBMODULES < <(git -C "$SOURCE_DIR" config --file .gitmodules --get-regexp '\.path$' | awk '{print $2}')

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT
SNAP="$WORKDIR/snapshot"
mkdir "$SNAP"
# Without git metadata, submodule configuration, workflows and the archive attributes.
rsync -a --exclude='.git' --exclude='.gitmodules' --exclude='/.github' --exclude='/.gitattributes' \
    "$SOURCE_DIR/" "$SNAP/"
# The uninitialised submodules are empty folders: remove them.
for path in "${SUBMODULES[@]}"; do
    [ -d "$SNAP/$path" ] || continue
    [ -z "$(find "$SNAP/$path" -mindepth 1 -print -quit)" ] || fail "Code of the submodule ${path} found in the snapshot."
    rmdir "$SNAP/$path"
done
# Ignore the folders of the paid elements and addons, so that they do not show up in git when added to a deployment.
for path in "${SUBMODULES[@]}"; do
    sed -i "\#^!/${path}/\$#d" "$SNAP/.gitignore"
done

RELEASE="$(sed -n "s/^\$plugin->release *= *'\([^']*\)'.*/\1/p" "$SNAP/version.php")"
VERSION="$(sed -n 's/^\$plugin->version *= *\([0-9]*\).*/\1/p' "$SNAP/version.php")"

for br in $BRANCHES; do
    echo "== ${SOURCE_REF} (${SHORT_SHA}) -> ${br} =="
    clone="$WORKDIR/clone-${br}"
    git clone --quiet --no-tags "$TARGET_URL" "$clone"
    (
        cd "$clone"
        git config user.name "$ACTOR"
        git config user.email "${ACTOR}@users.noreply.github.com"
        if git ls-remote --exit-code --heads origin "$br" >/dev/null 2>&1; then
            git checkout --quiet "$br"
        else
            git checkout --quiet --orphan "$br"
        fi
        git rm -rqf . >/dev/null 2>&1 || true
        rsync -a "$SNAP/" ./
        git add -A -f
        if git diff --cached --quiet; then
            echo "No changes for ${br}."
        else
            git commit --quiet -m "mod_contentdesigner ${RELEASE} (${VERSION}) from ${SOURCE_REF} (${SHORT_SHA})"
            git push --quiet origin "HEAD:refs/heads/${br}"
            echo "Pushed ${br}."
        fi
    )
done
