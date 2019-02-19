# frontender.platform-core

Copyright (C) 2017-2019 Dipity B.V., The Netherlands
All rights reserved.

Frontender is a web application development platform consisting of a
desktop application (Frontender Desktop) and a web application which
consists of a client component (Frontender Platform) and a core
component (Frontender Platform Core).

Frontender Desktop, Frontender Platform and Frontender Platform Core
may not be copied and/or distributed without the express
permission of Dipity B.V.

### Importers

#### csv.routes.js

This is a redirects importer that will get all the redirects from a CSV file.
The CSV file has a specific markup, you may not deviate from this markup:

```
<resource>,<destination>,<status>,<type>
```

The import is called from the build directory (where also the .env file resides)
and is called as follows:

```bash
node ./vendor/frontender/platform-core/importers/csv.routes.js <csv-file-path>
```

The csv-file-path can be a relative or absolute path.
After this file has executed all the redirects are imported and a notification is given.
