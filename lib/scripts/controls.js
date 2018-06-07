const { sync } = require('glob');
const { resolve, parse } = require('path');
const { randomBytes, createHash } = require('crypto');
const { unlinkSync } = require('fs');
require('dotenv/config');

(async function() {
    // Get all the controls
    let client = await require('mongodb').MongoClient.connect(`mongodb://${process.env.MONGO_HOST}:${process.env.MONGO_PORT}`),
        db = client.db(process.env.MONGO_DB),
        collection = db.collection('controls');

    let coreControls = sync(`${process.cwd()}/core/blueprints/controls/**/*.json`),
        projectControls = sync(`${process.cwd()}/project/blueprints/controls/**/*.json`),
        controls = coreControls.concat(projectControls).map(function(controlPath) {
            // Compute the type.
            let controlJson = require(controlPath),
                parts = parse(controlPath),
                dir = parts.dir.replace(`${process.cwd()}/`, '').replace(/(blueprints\/controls[\/]?)/, ''),
                control = {
                    revision: {
                        date: (new Date()).toISOString(),
                        user: {},
                        hash: createHash('md5').update(JSON.stringify(controlJson)).digest('hex'),
                        lot: randomBytes(16).toString('hex')
                    },
                    definition: controlJson,
                    identifier: `${dir}/${parts.name}`.replace(/\/+/, '/')
                };

            return control;
        });

    await collection.insertMany(controls);

    coreControls.concat(projectControls).map(function(controlPath) {
        unlinkSync(controlPath);
    });

    console.log('Done!');
    client.close();
})();