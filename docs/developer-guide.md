---
exclude: true
---

# Content Designer — Developer guide

How Content Designer is built, for people changing or extending it.

## Components

| Component | Directory | Purpose |
| --- | --- | --- |
| `mod_contentdesigner` | `mod/contentdesigner` | The activity, the editor and the free elements (public) |
| `cdelement_*` | `mod/contentdesigner/cdelement/*` | Elements (subplugin type `cdelement`) |
| `cdaddon_*` | `mod/contentdesigner/cdaddon/*` | Addons (subplugin type `cdaddon`), extending the activity through hooks |

Free elements: chapter, heading, paragraph, richtext, callout, image, tableofcontents, videotime, h5p, outro. The paid
elements (poll, question, rating, panels) and addons (report, pro) live in private repositories, referenced as git submodules;
`.gitignore` keeps any other code under `cdelement/` and `cdaddon/` out of this repository. The bundle with all of them
is published from the private repository `moodle-mod_contentdesigner_pro`.

## Data model

- `contentdesigner`: the activity, including the settings of the pro features (`navigation`, `completionendreach`,
  `completionmandatory`, `enablegrading`, `autostartattempts`, `grade`, `grademethod`, `attemptsallowed`). The core
  stores them; only the addon `cdaddon_pro` uses them.
- `contentdesigner.displaymode`: all chapters on one page (0) or every chapter on its own page (1, paged display,
  `local\pages`). The pages follow the gating of the scrolling display (chapter completion, mandatory elements, hook
  `content_navigation`); the page links continue the attempt shown (`action=continueattempt`), so addons that manage
  attempts keep showing the content.
- `contentdesigner_elements`: the installed element types (shortname → id).
- `cdelement_<shortname>`: the instances of an element, each with `contentdesignerid`, `title`, `visible`.
- `contentdesigner_content`: the elements in chapters (`element`, `instance`, `chapter`, `position`); the chapter keeps
  the order in `cdelement_chapter.contents`.
- `contentdesigner_options`: the general settings of each element instance (`element`, `instance`).
- `contentdesigner_attempts`: attempts of learners. Without addons, every learner has one attempt per activity
  (`local\attempts::get_current()`); elements store their progress per attempt (`cdattemptid`).
- `contentdesigner_completion`: the state of the completion rules of `cdaddon_pro` and the start time (report).

## Elements

An element is a subplugin `cdelement_<name>` with a class `cdelement_<name>\element` extending
`mod_contentdesigner\elements`. It implements `element_name()`, `element_shortname()`, `element_form()` and `render()`,
and overrides what it needs:

- `supports_content()`, `supports_multiple_instance()`, `supports_standard_elements()`, `supports_top_instance()`
  (with `get_editor_data()` and `render_module_topcontents()`), `supports_custom_form()`, `supports_reports()`.
- `validation($data, $files)`: errors of the element's own settings in its form.
- `prevent_nextelements($instance)`: whether the element hides the elements after it (mandatory elements).
- `supports_grade()` with `get_mark()`, `get_max_mark()` and `pre_process_grade()` for graded attempts.
- `delete_instance_data()`, `reset_userdata()`, `delete_attempt_data()`: delete the data of learners.
- `define_report_columns()`, `col_report_field()`: columns in the activity report.
- `search_area_list()`: the text field indexed by global search.

After storing the progress of a learner, an element calls `$this->progress_updated($userid)`.

Privacy: elements that store personal data implement `mod_contentdesigner\privacy\contentdesignerelements_provider`
(see `cdelement_h5p\privacy\provider`). Backup and restore: subplugin classes in `backup/moodle2`, with the mapping
`<shortname>_instanceid`; user data refers to attempts through the mapping `contentdesigner_attempt`; contents with
links are declared in the static `define_decode_contents()` of the restore class.

## Access checks

`mod_contentdesigner\local\access` resolves the activity of a request and checks the capability (`from_context()` for
fragments, `from_cmid()` for web services). Fragments only render; changes go through write web services, e.g. the
editor's `mod_contentdesigner_delete_element`, `_move_element`, `_move_chapter` and `_update_visibility`
(`db/services.php`), after which the editor renders the content again with the fragment `editor_content`. Every id from a request is loaded through the activity that was checked
(`get_element_instance()`, `get_chapter()`, `get_attempt()`, `contents_belong_to()`).

Guests may view the content (`mod/contentdesigner:view` is allowed for the guest role), but all guests share one
user account, so they cannot save progress: they view the content in an attempt that is not stored
(`local\attempts::guest_attempt()`, id 0), get no button to complete chapters, and web services that store progress
refuse them with `access::require_not_guest()`. Elements and addons that store progress of the current user call it
as well.

## Hooks

Addons extend the activity through these hooks in `mod_contentdesigner\hook` (callbacks in the addon's `db/hooks.php`):

| Hook | Dispatched | Used for |
| --- | --- | --- |
| `supports_feature` | `contentdesigner_supports()` | Grades (FEATURE_GRADE_HAS_GRADE, FEATURE_GRADE_OUTCOMES) |
| `completion_rules` | custom completion, form, cm info | Custom completion rules (`local\completion_rule`) |
| `extend_mod_form`, `mod_form_validation` | activity form | Settings of the activity |
| `after_instance_saved`, `before_instance_deleted` | `lib.php` | Grade items, own data |
| `user_grades` | `contentdesigner_update_grades()` | Gradebook grades |
| `view_page` | activity page (`content_display`) | Taking over the page, e.g. attempts |
| `extend_content` | learner content | HTML below the outro, JavaScript |
| `content_navigation` | chapters | Free navigation |
| `chapter_completion` | chapters | Automatic chapter completion |
| `extend_element_form` | element forms | Settings of elements |
| `outro_button_types`, `outro_button` | outro | More outro buttons |
| `h5p_player` | H5P element | xAPI component for saved states |
| `progress_updated` | `elements::progress_updated()` | Reacting to progress, e.g. completion |
| `extend_settings_navigation` | activity navigation | Pages of addons, e.g. reports |

`local\extensions` dispatches them and caches the answers that do not depend on an activity.

## Tests and checks

The data generator has helpers for content: `create_chapter()`, `create_element()`, `create_options()` and
`create_attempt()`. Tests that depend on addons redirect the hooks (`redirectHook()`) so that they pass with and
without the addons installed. CI runs on pull requests, on pushes to `main` and by hand, for Moodle 5.2 and 5.3, with and without Video Time
(`.github/workflows/moodle-plugin-ci.yml`).

An element that depends on an optional plugin overrides `is_available()`: while it returns false the element is not
offered in the editor and cannot be added or saved, and its instances should render a notice (see
`cdelement_videotime`).

## Releases

Tags `vX.Y-rN`, with a section `## <release> (<version>)` in `CHANGES.md` (`## <release> R<n> (<version>)` from R2
on). Publishing a GitHub release runs `release.yml`: it attaches the ZIP of the free plugin (without the submodules)
and sends it to the shop of bdecent.de; a pre-release goes to staging.bdecent.de only. The Moodle Marketplace step is
off until the plugin is listed there. Then the workflow `stable-branches.yml` (started by hand with the tag) builds the
`MOODLE_502_STABLE` and `MOODLE_503_STABLE` branches for git deployments, and the publish workflow of
`moodle-mod_contentdesigner_pro` builds the bundle with the paid elements and addons. The availability condition is
released from its own repository with the same tag.
