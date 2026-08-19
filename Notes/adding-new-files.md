# How to add a new story going forward:

1. Write your story as `projects/<project-slug>/<story-slug>/<story-slug>.md`
   (create the folder if the story is new). If you have a Spanish translation
   ready, save it alongside as `<story-slug>-es.md`.

2. Add one entry to `$STORIES` in `partials/content.php`:

   ```php
   'your-slug' => array(
     'project' => 'parent-project-slug',
     'title'   => array('en' => 'English Title', 'es' => 'Título en español'),
     'type'    => array('en' => 'fiction', 'es' => 'ficción'),
     'desc'    => array(
       'en' => 'English blurb.',
       'es' => 'Descripción en español.',
     ),
     'langs' => array('en'),          // add 'es' once the -es.md exists
   ),
   ```

   A story with only `'en'` in `langs` simply won't appear on any Spanish
   listing page (homepage, project page, random) until you add `'es'` —
   that's the whole mechanism for "not every story is translated yet".

3. Create `projects/<project-slug>/<story-slug>/index.php` containing just:

   ```php
   <?php
   $storySlug = '<story-slug>';
   require __DIR__ . '/../../../partials/content.php';
   $story = $STORIES[$storySlug];
   include __DIR__ . '/../../../partials/story-shell.php';
   ```

   (paths are relative to this file's own location — `__DIR__`, never
   `$_SERVER['DOCUMENT_ROOT']`, which turned out to be unreliable on the live
   host and caused a site-wide 500 the first time this was deployed)

That's it. The story will automatically show up in its project's own listing
page (in both languages), the homepage's "latest works" table, and
`random.php` — nothing else to update by hand. If it should also appear in
the homepage's "my favourites" table, add its slug to `$FAVOURITES` in
`partials/content.php` too.

## Adding a new project

Add one entry to `$PROJECTS` in `partials/content.php` — `title`, `type`,
`desc`, and `count` (the story-count label, e.g. `'2 stories'`/`'2 historias'`)
each as `array('en' => ..., 'es' => ...)`. If it's a plain story-listing
project (not a custom SPA like `postcords-archive` or `the-post-within`),
create `projects/<project-slug>/index.php` following the pattern of the
existing ones (`unclassified`, `mirror-self`, `pananormales`):

```php
<?php
$projectSlug = '<project-slug>';
require __DIR__ . '/../../partials/content.php';
$project = $PROJECTS[$projectSlug];
include __DIR__ . '/../../partials/project-shell.php';
```

## Projects

Every project renders through `partials/project-shell.php`: a description, a
`// series` block (when it has series), and a `// stories` table. The admin
portal's `PROJECTS` tab edits all of it — title/type, the description that
heads the page, the singular/plural nouns behind the "N stories" label, and
the order the stories appear in.

Story order works like series parts: ones you place by hand come first in that
order, everything else stays newest-first, so a project nobody has touched
looks exactly as it always did. Because a story belongs to exactly one
project, adding a story to a project from that tab *moves* it — its folder
moves on disk and it drops out of any series it was in.

> A project can still render its own page instead of `project-shell.php` —
> `postcords-archive` is the terminal, and its `index.php` draws its story
> list from the same registry as everything else, in the order the PROJECTS
> tab sets. That's the pattern to copy for any future custom page: keep the
> markup, build the data from `project_stories()`, never hardcode a list.
> `the-post-within` was a placeholder newspaper front page and now uses the
> standard project page; its old markup is in git history.
> `cms_projects.is_custom_spa` is a retired flag — nothing reads it any more,
> and a custom page no longer stops stories or series being filed there.

## Series

A **series** is a reading order inside one project — a project can hold
several series, a series never spans projects, and a series never contains a
project. They're managed entirely from the admin portal's `SERIES` tab (and
from the `Series:` picker in the story editor); nothing here needs editing by
hand.

- A series lives at `/projects/<project>/series/<series-slug>/`, backed by
  `projects/<project>/series/<series-slug>/index.php` — written by the CMS
  when the series is created, same as a story's `index.php`. The `series/`
  path segment is why no story may be slugged `series`.
- Its page shows the series description and every part in reading order.
- Order comes from the part number the admin assigns each story; stories left
  without one follow the numbered ones in upload order (oldest first).
  Dragging the list around in the series editor just rewrites those numbers.
- A project page grows a `// series` block above `// stories` as soon as one
  of its series has a part readable in the current language, and a story
  that's part of one gets a link to the series under its title plus
  previous/next-part buttons at the end of the text.
- Untranslated parts are skipped in the Spanish reading order rather than
  dead-ending the reader, exactly like the other listings.

## Redacted text

Each story has a **redact** toggle in the admin editor. With it on, every
literal `[REDACTED]` in that story's markdown renders glitched — blurred,
struck through, italic (see `.redacted` in `style.css`). Off by default, and
the marker is plain text everywhere else.

## How the Spanish site works

There's no separate set of Spanish files or pages — `/es/<anything>` is
rewritten internally (see `.htaccess`) to serve the exact same PHP file as
`/<anything>`. Each file detects the language itself from the request URL
(`$lang`, set in `partials/content.php`) and renders accordingly, pulling
localized strings from each registry entry's `'es'` key and the `$UI` array
in `partials/content.php` (nav labels, section headers, footer text, etc.).

This means the two custom SPA pages (`postcords-archive`, `the-post-within`)
are reachable at their `/es/...` URL too, but each handles that itself in its
own inline `<script>` rather than through `partials/content.php` — they read
`isEs` off `window.location.pathname` and swap their own UI strings/content.
`postcords-archive`'s terminal is fully bilingual (own translation table,
falls back to a "not available in this language" notice per-story if a
translation is missing). `the-post-within` has no Spanish content at all yet,
so its `/es/...` URL shows a "not available" notice instead of pretending to
be translated.

*To test locally:* a real Apache + PHP + mod_rewrite setup is installed via
XAMPP (see project notes/conversation history for setup), with `DocumentRoot`
pointed at this folder — so `http://localhost/` (and `http://localhost/es/`)
behaves exactly like production, `.htaccess` included. Start it with
`C:\xampp\apache\bin\httpd.exe`.
