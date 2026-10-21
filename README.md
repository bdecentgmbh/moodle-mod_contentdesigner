# Content Designer

Content Designer is a Moodle activity in which teachers build learning content from elements grouped in
chapters: headings, paragraphs, rich text and interactive H5P content, closed by an outro. Learners work through
the chapters one after the other while a progress bar shows how far they are.

## Features

- **Content editor**: add, edit, move, hide, duplicate and delete elements and chapters; rename them in place.
- **Elements**: chapter, heading, paragraph, rich text, interactive content (H5P) and outro (image, text and up to
  two buttons).
- **Design**: every element has a description, margin and padding, backgrounds, an entrance animation, scrolling
  effects and responsive visibility, with site-wide defaults.
- **Mandatory elements**: an H5P element can hide the elements after it until the learner completed it.
- **Chapters** that learners mark as done, with bookmarks and notes when
  [Learning Tools](https://moodle.org/plugins/local_learningtools) is installed.
- **Global search**, **backup and restore**, **course reset**, **privacy API**, **Moodle 4.5 to 5.3**.
- **Subplugins**: elements (`mod/contentdesigner/cdelement`) and addons (`mod/contentdesigner/cdaddon`), which
  extend the activity through hooks.

## Content Designer bundle

The paid Content Designer bundle by [bdecent](https://bdecent.de) contains this plugin and adds the elements image,
poll, question, rating, table of contents and Video Time, the activity report, and the pro features: free navigation,
graded attempts, completion by reaching the end or by completing the mandatory elements, automatic chapter
completion and saved H5P states.

## Requirements

- Moodle 4.5 to 5.3

## Installation

Install it like any other activity module, into `mod/contentdesigner`, then visit *Site administration ▸
Notifications*. To deploy from git, use the `MOODLE_xxx_STABLE` branch of your Moodle version (for example
`MOODLE_405_STABLE`); `main` references the paid elements and addons as git submodules.

Upgrading from 1.x keeps all content; see the [administrator guide](docs/admin-guide.md#upgrading-from-content-designer-1x).

## Documentation

- [Administrator guide](docs/admin-guide.md): settings, permissions, backups, personal data
- [Teacher guide](docs/teacher-guide.md): the content editor and the elements
- [Student guide](docs/student-guide.md)
- [Developer guide](docs/developer-guide.md): elements, addons, hooks

## License

2026 bdecent GmbH <https://bdecent.de>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
version.
