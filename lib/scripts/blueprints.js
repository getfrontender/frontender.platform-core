/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

const { sync } = require('glob');
const { resolve, parse, format } = require('path');
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
                blueprint = moveContainers(blueprintJson),
                parts = parse(containerPath);

            parts.ext = '.png';
            parts.base = `${parts.name}${parts.ext}`;

            return {
                revision: {
                    user: {},
                    date: (new Date()).toISOString(),
                    hash: createHash('md5').update(JSON.stringify(blueprint)).digest('hex'),
                    lot: randomBytes(16).toString('hex'),
                    type: 'container',
                    thumbnail: `data:image/png;base64,${readFileSync(format(parts)).toString('base64')}`
                },
                definition: blueprint
            };
        });

    await collection.insertMany(blueprints);

    // blueprintPaths.map(function(containerPath) {
    //     unlinkSync(containerPath);
    // });

    console.log('Done!');
    client.close();
})();