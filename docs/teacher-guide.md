# Content Designer — Teacher guide

Content Designer is an activity in which you build learning content from elements, grouped in chapters: headings,
paragraphs, rich text and interactive H5P content, closed by an outro. Learners work through the content chapter by
chapter; a progress bar shows how far they are.

## Adding a Content Designer activity

1. Turn editing on in your course.
2. Choose *Add an activity or resource* and pick **Content Designer**.
3. Enter a **Name** and, optionally, a **Description**.
4. Under **Display**, choose the **Display mode**: **Scroll** (default) shows all chapters on one page, **Pages** shows
   every chapter on its own page with the buttons *Previous page* and *Next page*.
5. Set the completion conditions under **Completion conditions** and save.

With **Pages**, learners reach the next page under the same conditions as the next chapter when scrolling: they
complete the chapter (*Mark as done*) and its mandatory elements. When they return, they continue on the page where
their progress stops; a new attempt starts on the first page. The outro is shown on the last page.

Addons may add more settings to this form, for example the navigation method or grading.

## The content editor

Open the activity and choose **Content editor** in its navigation. The editor shows the chapters with their elements
and the outro at the end.

- **Add**: the plus buttons add an element at the top or the bottom of a chapter, or a new chapter. Pick the element
  in the list, fill in its settings and save.
- **Edit**: the cog of an element opens its settings. Click the title of an element to rename it in place.
- **Move**: the arrows move an element up or down, also into the previous or next chapter, and move chapters.
- **Show or hide**: the eye hides an element from learners without deleting it.
- **Duplicate**: copies an element, or a chapter with all its elements.
- **Delete**: deletes the element and the answers of the learners in it.

## Elements

| Element | What it shows |
| --- | --- |
| Chapter | Groups elements. Settings: title, whether learners see the title, visibility, Learning Tools (bookmarks and notes, when the Learning Tools plugin is installed). |
| Heading | A main heading (h2) or sub heading (h3), optionally as a link, with its alignment. |
| Paragraph | Plain text with its alignment. |
| Rich text | Text with formatting, images and media, written in the editor. |
| Interactive content (H5P) | An H5P package. Set it as **Mandatory** to hide the elements after it until the learner completed it. |
| Outro | The end of the content: an image, a text and up to two buttons (custom link, next activity, back to the course or the section). |

The paid elements and addons of the Content Designer bundle add more elements (for example images, polls, questions,
ratings, a table of contents, Video Time videos) and more settings.

## General settings of elements

Most elements have the same general settings, below their own ones:

- **Title** and **Description** (shown above the element when **Show description** is checked).
- **Visibility**.
- **Margin** and **Padding**: one to four CSS lengths, for example `10px` or `0 1rem 5% auto`.
- **Background**: a colour or gradient below and above, for example `#e66465` or
  `linear-gradient(#e66465, #9198e5)`, and a background image.
- **Animation**: an entrance animation (fade in, slide in), its duration and delay.
- **Scrolling effects**: the element moves horizontally while learners scroll; direction, speed and the viewport
  position where it starts.
- **Responsive**: hide the element on desktops, tablets or phones.

The defaults of these settings are set by the administrator.

## Chapters and mandatory elements

Learners see the chapters one after the other: a chapter is shown once the previous one is completed. Learners
complete a chapter with its **Mark as done** button at the end of the chapter.

Inside a chapter, an element set as **Mandatory** (for example an H5P element) hides the elements after it until the
learner completed it. Teachers always see the whole content.

## Completion

Besides viewing the activity, addons may add completion conditions, for example reaching the end of the content or
completing all mandatory elements.

## What learners see

Learners see the content as described in the [student guide](student-guide.md): the progress bar on top, the chapters
they reached and the outro once they completed all chapters.
