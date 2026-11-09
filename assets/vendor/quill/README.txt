OPTIONAL - this folder may stay empty.

The page editor (Admin > Pages) uses Quill 2.0.3. With nothing installed here it
loads these two files from the jsDelivr CDN:

  https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js
  https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css

To serve Quill from your own domain instead (no third-party request), run:

  php cron/install-quill.php

or download the same two files and save them in this folder as
quill.js and quill.snow.css (exact names, version 2.0.3).
