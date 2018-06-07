const { sync } = require('glob');
const { resolve, parse, format } = require('path');
const { randomBytes, createHash } = require('crypto');
const { unlinkSync, readFileSync } = require('fs');
require('dotenv/config');

(async function() {
    // Get all the controls
    let client = await require('mongodb').MongoClient.connect(`mongodb://${process.env.MONGO_HOST}:${process.env.MONGO_PORT}`),
        db = client.db(process.env.MONGO_DB),
        collection = db.collection('pages');

    let pagePaths = sync(`${process.cwd()}/project/pages/published/**/*.json`),
        pages = pagePaths.map(function(pagePath) {
            // Compute the type.
            let page = require(pagePath);

            return {
                revision: {
                    user: {},
                    date: (new Date()).toISOString(),
                    hash: createHash('md5').update(JSON.stringify(page)).digest('hex'),
                    lot: randomBytes(16).toString('hex')
                },
                definition: page
            };
        });

    await collection.insertMany(pages);

    // blueprintPaths.map(function(containerPath) {
    //     unlinkSync(containerPath);
    // });

    console.log('Done!');
    client.close();
})();