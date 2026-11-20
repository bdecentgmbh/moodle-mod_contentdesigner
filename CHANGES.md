# Changelog

## 2.0 (2026-10-21)

Supports Moodle 4.5 to 5.3; requires Moodle 4.5.

- One code base for the free plugin and the paid bundle: the free plugin contains the core of the former pro
  edition, including its fixes and global search. The paid features are separate subplugins: the elements image,
  poll, question, rating, table of contents and Video Time, and the addons `cdaddon_report` (activity report) and
  `cdaddon_pro` (free navigation, graded attempts, completion rules, automatic chapter completion, saved H5P states).
- Addons extend the activity through hooks (`classes/hook`); the settings and data of the pro features stay in the
  tables of the activity, so upgrading from the pro edition 1.x keeps them.
- Security: every element, chapter, content and attempt id of a request is loaded through the activity whose access
  was checked, in pages, fragments, web services, the in-place title editing and the file serving; element settings
  accept CSS lengths, colours and gradients only; the H5P web services accept only the own unfinished attempt.
- Privacy: elements export and delete the data of learners through an extended interface
  (`contentdesignerelements_provider`), with user lists; the activity covers attempts and completion states.
- Backup and restore include the attempts with the data of the elements and the general settings of all elements,
  and restore links in element texts.
- Course reset deletes the progress of learners; deleting an activity or an element deletes the data of learners in
  it.
- Global search indexes visible elements only and checks access without extra tables.
- Paged display (setting "Display mode"): every chapter on its own page with Previous and Next buttons, under the
  same conditions as the scrolling display (chapter completion, mandatory elements); learners continue on the page
  where their progress in the attempt stops, a new attempt starts on the first page, the outro is on the last page.
- The editor uses `core/modal`, required by Moodle 5.2 and later.
- Documentation for the Documentation Center (`docs/`); tests and CI for Moodle 4.5 to 5.3; stable branches for
  deployments from git.

## 1.6 and earlier

See the git history.
