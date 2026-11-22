# Content Designer

Content Designer is a Moodle activity in which teachers build learning content from elements grouped in
chapters: headings, paragraphs, rich text, callouts, images, videos and interactive H5P content, closed by an outro.
Learners work through the chapters one after the other, on one page or page by page, while a progress bar shows how
far they are.

## Features

- **Editor**: add, edit, move, hide, duplicate and delete elements and chapters; rename them in place.
- **Elements**: chapter, heading, paragraph, rich text, callout (info, tip, warning and eight more types), image
  (slideshow and lightbox), table of contents, Video Time (when [Video Time](https://moodle.org/plugins/mod_videotime)
  is installed), interactive content (H5P) and outro (image, text and up to two buttons).
- **Display**: all chapters on one scrolling page, or one chapter per page with Previous and Next.
- **Design**: every element has a description, margin and padding, backgrounds, an entrance animation, scrolling
  effects and responsive visibility, with site-wide defaults.
- **Mandatory elements**: H5P and Video Time elements can hide the elements after them until the learner completed
  them.
- **Chapters** that learners mark as done, with bookmarks and notes when
  [Learning Tools](https://moodle.org/plugins/local_learningtools) is installed.
- **Global search**, **backup and restore**, **course reset**, **privacy API**, **Moodle 5.2 and 5.3**.
- **Subplugins**: elements (`mod/contentdesigner/cdelement`) and addons (`mod/contentdesigner/cdaddon`), which
  extend the activity through hooks.

## Content Designer bundle

The paid Content Designer bundle by [bdecent](https://bdecent.de) contains this plugin and adds the elements poll,
question, rating and panels (tabs and accordions), the activity report, and the pro features: free navigation,
graded attempts, completion by reaching the end or by completing the mandatory elements, automatic chapter
completion and saved H5P states. It is available in the [bdecent shop](https://bdecent.de).

## Requirements

- Moodle 5.2 or 5.3

## Installation

Install it like any other activity module, into `mod/contentdesigner`, then visit *Site administration ▸
Notifications*. To deploy from git, use the `MOODLE_xxx_STABLE` branch of your Moodle version (for example
`MOODLE_503_STABLE`); `main` references the paid elements and addons as private git submodules. Releases (ZIP) are
published on GitHub.

Upgrading from 1.x keeps all content; see the [administrator guide](docs/admin-guide.md#upgrading-from-content-designer-1x).

## Documentation

- [Administrator guide](docs/admin-guide.md): settings, permissions, backups, personal data
- [Teacher guide](docs/teacher-guide.md): the editor and the elements
- [Student guide](docs/student-guide.md)
- [Developer guide](docs/developer-guide.md): elements, addons, hooks

## License

2026 bdecent GmbH <https://bdecent.de>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
version.
