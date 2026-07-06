# How to add a new story going forward:

1. Write your story as `projects/<project-slug>/<story-slug>/<story-slug>.md`
   (create the folder if the story is new).

2. Add one entry to `$STORIES` in `partials/content.php` — title, type, desc,
   `project` (the parent project's slug), and `langs` (e.g. `array('en')`).

3. Create `projects/<project-slug>/<story-slug>/index.php` containing just:

   ```php
   <?php
   $storySlug = '<story-slug>';
   require __DIR__ . '/../../../partials/content.php';
   $story       = $STORIES[$storySlug];
   $pageTitle   = $story['title'] . ' — A Cuervoz';
   $storyTitle  = $story['title'];
   $storyType   = $story['type'];
   $projectName = $PROJECTS[$story['project']]['title'];
   $mdFile      = $storySlug . '.md';
   include __DIR__ . '/../../../partials/story-shell.php';
   ```

   (paths are relative to this file's own location — `__DIR__`, never
   `$_SERVER['DOCUMENT_ROOT']`, which turned out to be unreliable on the live
   host and caused a site-wide 500 the first time this was deployed)

That's it. The story will automatically show up in its project's own listing
page, the homepage's "latest works" table, and `random.php` — nothing else to
update by hand. If it should also appear in the homepage's "my favourites"
table, add its slug to `$FAVOURITES` in `partials/content.php` too.

## Adding a new project

Add one entry to `$PROJECTS` in `partials/content.php` (title, type, desc).
If it's a plain story-listing project (not a custom SPA like
`futuristic-historical` or `the-post-within`), create
`projects/<project-slug>/index.php` following the pattern of the existing
ones (`unclassified`, `mirror-self`, `pananormales`) — it pulls its story list
straight from `$STORIES`, filtering on `project`.

*To test locally:* a real Apache + PHP + mod_rewrite setup is installed via
XAMPP (see project notes/conversation history for setup), with `DocumentRoot`
pointed at this folder — so `http://localhost/` behaves exactly like
production, `.htaccess` included. Start it with `C:\xampp\apache\bin\httpd.exe`.
