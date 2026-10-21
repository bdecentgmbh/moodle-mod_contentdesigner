---
pagetypes:
  - mod-contentdesigner-*
  - admin-setting-elementgeneralsettings
  - admin-setting-cdelement_*
  - admin-setting-cdaddon_*
  - admin-category-modcontentdesigner
---

# Content Designer — Administrator guide

Content Designer is an activity in which teachers build learning content from elements in chapters. This guide covers
installing and configuring it, the permissions, and what happens with backups, course resets and personal data.

## Requirements and installation

- Moodle 4.5, 5.1, 5.2 or 5.3.
- Optional: [Learning Tools](https://moodle.org/plugins/local_learningtools) for bookmarks and notes on chapters.

Install it like any other activity module into `mod/contentdesigner`, then visit *Site administration ▸
Notifications*. To deploy from git, use the `MOODLE_xxx_STABLE` branch of your Moodle version of
`bdecentgmbh/moodle-mod_contentdesigner` (for example `MOODLE_405_STABLE`); `main` references the paid elements and
addons as git submodules.

## The Content Designer bundle

The free plugin contains the activity with the elements chapter, heading, paragraph, rich text, H5P and outro. The paid
Content Designer bundle adds elements (image, poll, question, rating, table of contents, Video Time) and addons: the
activity report and the pro features (navigation method, graded attempts, completion rules, automatic chapter
completion, saved H5P state). Install the bundle instead of the free plugin; it contains the free plugin.

### Upgrading from Content Designer 1.x

- From the free edition 1.x: install 2.0 over it and run the upgrade. All content stays.
- From the pro edition 1.x: deploy the bundle 2.0 (from git, the stable branch of your Moodle version of the private
  bundle repository instead of its `main`) and run the upgrade. The paid elements keep their data; the addon
  `cdaddon_pro` takes over the settings and data of the pro features, which stay in the tables of the activity.
  Nothing has to be uninstalled.

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
| `mod/contentdesigner:viewcontenteditor` | Editing teacher, manager | Using the content editor (RISK_XSS: rich text is shown as entered, like in a page) |

Elements and addons may define their own capabilities.

## Backup, restore and course reset

- Backups contain the content (chapters, elements, their settings and files). With user data, they also contain the
  attempts, completed chapters and the answers of the learners. Elements that are not installed on the target site
  are skipped on restore.
- A course reset can delete the progress of all learners (*Delete the progress of all learners*).

## Personal data

The activity stores the attempts and the completion state of learners; the elements store their progress and answers
(for example completed chapters, H5P results and saved H5P states). All of it is exported and deleted through the
privacy API. Gradebook grades are handled by the gradebook.

## Global search

The search area *Content Designer elements* indexes the titles and texts of visible elements; results are shown only
to users who can see the activity, the element and its chapter.
