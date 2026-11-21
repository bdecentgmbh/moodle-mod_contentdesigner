# Changelog

## 2.0 (2026112002)

Supports Moodle 5.2 and 5.3; requires Moodle 5.2.

- One code base for the free plugin and the paid bundle: the free plugin contains the core of the former pro
  edition, including its fixes and global search. The elements image, table of contents and Video Time, formerly
  paid, are now free. The paid features are separate subplugins in private repositories: the elements poll,
  question, rating and panels, and the addons `cdaddon_report` (activity report) and `cdaddon_pro` (free navigation,
  graded attempts, completion rules, automatic chapter completion, saved H5P states).
- New element callout (free): notes (information, tip, warning, danger, success, example) as tinted boxes, and own
  layouts for speech bubble, expert quote, definition, reflection prompt and key takeaway.
- The activity type is called "Content" (plugin name stays Content Designer), the link to the editor "Editor"; new
  activity icon.
- New element panels (paid): tabs or an accordion; a mandatory panels element hides the next elements until every
  panel was opened.
- Paged display (setting "Display mode"): every chapter on its own page with Previous and Next buttons, under the
  same conditions as the scrolling display (chapter completion, mandatory elements); learners continue on the page
  where their progress in the attempt stops, a new attempt starts on the first page, the outro is on the last page.
- The Video Time element no longer requires Video Time: without it the element is not offered and existing
  elements show a notice.
- Addons extend the activity through hooks (`classes/hook`); the settings and data of the pro features stay in the
  tables of the activity, so upgrading from the pro edition 1.x keeps them.
- Security: every element, chapter, content and attempt id of a request is loaded through the activity whose access
  was checked, in pages, fragments, web services, the in-place title editing and the file serving; files of hidden
  elements and chapters are served to editors only; element settings accept CSS lengths, colours and gradients only;
  the H5P web services accept only the own unfinished attempt. Rich text, callouts and panels are cleaned unless
  their author may trust content (`moodle/site:trustcontent`); rich text saved before 2.0 stays trusted until it is
  edited.
- Security: learners can complete only the chapters they reached, after the mandatory elements before and in them
  (sequential navigation); the chapters after a chapter are loaded only if the learner can get past it.
- Guests view the content without saving progress (no attempts, chapter completions or H5P results); a notice
  asks them to log in.
- The editor saves deleting, moving and hiding elements and moving chapters through write web services.
- Outro buttons with a URL that is not valid are not shown; the editor shows the titles of the outro and the
  table of contents without HTML.
- Chapter titles are formatted for learners (filters, multi-language content).
- The chapter and the outro cannot be uninstalled; uninstalling another element deletes its contents, settings and
  files in all activities. Deleting an element deletes the files of all its areas.
- Global search indexes an element again after it is shown or renamed in the editor.
- Editing or duplicating an element or a chapter keeps the stored description (multi-language text, links of
  filters and file links were saved as displayed before); duplicated chapters copy their description files and
  background image.
- A hidden outro is not shown to learners; the end of the content is still marked for the completion rule.
- Element settings are checked against the size of their database columns (margins, paddings, backgrounds, delay,
  title), so saving no longer fails on PostgreSQL or strict MySQL.
- Backup and restore: links to Content Designer activities and their course point to the restored ones (also in
  element descriptions and paragraphs); the maximum score of H5P elements is kept; the table of contents no longer
  restores a second, wrongly linked copy of its options.
- The upgrade from the free edition 1.x no longer passes its state between plugins in the session.
- The unused site setting for a default background image of elements is removed.
- Privacy: elements export and delete the data of learners through an extended interface
  (`contentdesignerelements_provider`), with user lists; the activity covers attempts and completion states.
- Backup and restore include the attempts with the data of the elements and the general settings of all elements,
  and restore links in element texts.
- Course reset deletes the progress of learners; deleting an activity or an element deletes the data of learners in
  it.
- Global search indexes visible elements only and checks access without extra tables.
- Accessibility: the element picker of the editor works with the keyboard; element icons have names.
- The editor uses `core/modal`, required by Moodle 5.2 and later.
- Documentation for the Documentation Center (`docs/`); tests and CI for Moodle 5.2 and 5.3, with and without
  Video Time; stable branches for deployments from git; release workflow.

## 1.6 and earlier

See the git history.
