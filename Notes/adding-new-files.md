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
