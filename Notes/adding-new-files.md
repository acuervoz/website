# How to add a new story going forward:

1. Write your story as projects/your-title.md

2. Copy any existing shell HTML, change the <title>, <h1>, <div class="page-meta">, and the fetch('...') filename

3. Add a row to the table in index.html, projects.html, and projects/index.html
   (projects/index.html is the same listing, with paths adjusted since it lives
   one folder deeper — this is what loads when someone visits /projects/ with
   a trailing slash)

4. Add the path to the pieces array in random.html

*To test locally:* use VS Code's Live Server extension (right-click index.html → "Open with Live Server") or run python -m http.server in the project folder. Direct file:// opening will block the fetch() call.