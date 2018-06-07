const { sync } = require('glob');
const { resolve, parse } = require('path');
const { randomBytes, createHash } = require('crypto');
const { unlinkSync, readFileSync } = require('fs');
require('dotenv/config');

function moveContainers(container) {
    if(container.hasOwnProperty('template_config')) {
        if (container.template_config.hasOwnProperty('containers')) {
            // Move the containers
            container.containers = container.template_config.containers.slice(0);
            delete container.template_config.containers;
        }
    }

    if(container.hasOwnProperty('containers')) {
        container.containers = container.containers.map(moveContainers);
    }

    return container;
}

(async function() {
    // Get all the controls
    let client = await require('mongodb').MongoClient.connect(`mongodb://${process.env.MONGO_HOST}:${process.env.MONGO_PORT}`),
        db = client.db(process.env.MONGO_DB),
        collection = db.collection('blueprints');

    let blueprintPaths = sync(`${process.cwd()}/project/blueprints/containers/**/*.json`),
        blueprints = blueprintPaths.map(function(containerPath) {
            // Compute the type.
            let blueprintJson = require(containerPath),
                blueprint = moveContainers(blueprintJson);

            console.log(containerPath);
            console.log(blueprint);

            return {
                revision: {
                    user: {},
                    date: (new Date()).toISOString(),
                    hash: createHash('md5').update(JSON.stringify(blueprint)).digest('hex'),
                    lot: randomBytes(16).toString('hex'),
                    type: 'container'
                },
                definition: blueprint
            };
        });

    // await collection.insertMany(blueprints);
    //
    // blueprintPaths.map(function(containerPath) {
    //     unlinkSync(containerPath);
    // });

    console.log(blueprints.length);

    console.log('Done!');
    client.close();
})();