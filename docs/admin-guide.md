---
pagetypes:
  - mod-contentdesigner-*
  - admin-setting-elementgeneralsettings
  - admin-setting-cdelement_*
  - admin-category-modcontentdesigner
---

# Content Designer — Administrator guide

Content Designer is an activity in which teachers build learning content from elements in chapters. This guide covers
installing and configuring it, the permissions, and what happens with backups, course resets and personal data.

## Requirements and installation

- Moodle 5.2 or 5.3.
- Optional: [Learning Tools](https://moodle.org/plugins/local_learningtools) for bookmarks and notes on chapters.
- Optional: [Video Time](https://moodle.org/plugins/mod_videotime) for the Video Time element. Without it the element
  is not offered, and existing Video Time elements show a notice instead of the video.

Install it like any other activity module into `mod/contentdesigner`, then visit *Site administration ▸
Notifications*. To deploy from git, use the `MOODLE_xxx_STABLE` branch of your Moodle version of
`bdecentgmbh/moodle-mod_contentdesigner` (for example `MOODLE_503_STABLE`); `main` references the paid elements and
addons as git submodules.

## The Content Designer bundle

The free plugin contains the activity with the elements chapter, heading, paragraph, rich text, callout, image, table
of contents, Video Time, H5P and outro. The paid Content Designer bundle adds elements (poll, question, rating,
panels) and addons: the activity report and the pro features (navigation method, graded attempts, completion rules, automatic chapter
completion, saved H5P state). Install the bundle instead of the free plugin; it contains the free plugin.

### Upgrading from Content Designer 1.x

- From the free edition 1.x: install 2.0 over it and run the upgrade. All content stays.
- From the pro edition 1.x: deploy the bundle 2.0 (from git, the stable branch of your Moodle version of the private
  bundle repository instead of its `main`) and run the upgrade. The paid elements keep their data; the addon
  `cdaddon_pro` takes over the settings and data of the pro features, which stay in the tables of the activity.
  Nothing has to be uninstalled.
- The elements image, table of contents and Video Time were paid in 1.x and are part of the free plugin from 2.0 on.
  Their data stays.
- Rich text is now cleaned (scripts, iframes and other unsafe markup are removed) unless its author has the capability
  `moodle/site:trustcontent`. Rich text saved before 2.0 is shown as before until it is edited.

## Settings

*Site administration ▸ Plugins ▸ Activity modules ▸ Content Designer*:

- **Element general settings**: the defaults of the general settings of new elements (visibility, margin, padding,
  backgrounds, animation, scrolling effects, responsive visibility). Margins and paddings must be CSS lengths,
  backgrounds colours or gradients; other values are ignored.
- One page per element with its defaults, for example the default heading type or whether H5P elements are mandatory,
  and the default content and buttons of the outro.
- Addons add their own pages.

## Permissions

| Capability | Default | Allows |
| --- | --- | --- |
| `mod/contentdesigner:addinstance` | Editing teacher, manager | Adding the activity to a course |
| `mod/contentdesigner:view` | Guest, authenticated user | Viewing the content |
| `mod/contentdesigner:viewcontenteditor` | Editing teacher, manager | Using the editor (RISK_XSS: rich text is shown as entered, like in a page) |

Elements and addons may define their own capabilities.

## Backup, restore and course reset

- Backups contain the content (chapters, elements, their settings and files). With user data, they also contain the
  attempts, completed chapters and the answers of the learners. Elements that are not installed on the target site
  are skipped on restore.
- A course reset can delete the progress of all learners (*Delete the progress of all learners*).

## H5P content

H5P elements are played by Moodle's H5P player (`/h5p/embed.php`). It checks that the user may see the activity, but
not the visibility of the element or its chapter, nor whether the learner has reached it yet. A learner who knows the
address of an H5P package (its file name and the element) can therefore open a hidden or not yet reached H5P element
directly. Its results are saved only if the element is visible, and only in the learner's own attempt. The teacher
guide tells teachers not to rely on hidden H5P elements for confidential content.

## Personal data

The activity stores the attempts and the completion state of learners; the elements store their progress and answers
(for example completed chapters, H5P results and saved H5P states). All of it is exported and deleted through the
privacy API. Gradebook grades are handled by the gradebook.

## Global search

The search area *Content Designer elements* indexes the titles and texts of visible elements; results are shown only
to users who can see the activity, the element and its chapter.
