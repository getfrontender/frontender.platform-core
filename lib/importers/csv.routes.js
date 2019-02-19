#!/usr/bin/env node

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

(async () => {
    const path = require("path"),
        fs = require("fs"),
        Client = require("mongodb").MongoClient,
        config = require("dotenv").load(
            path.resolve(__dirname, "..", "..", "..", "..", "..", ".env")
        ),
        CSVParse = require("csv-parse"),
        connection = await Client.connect(config.parsed.MONGO_HOST),
        db = connection.db(config.parsed.MONGO_DB),
        chalk = require("chalk"),
        trim = (string, charList = "\\/") => {
            return string
                .toString()
                .replace(new RegExp(`^[${charList}]+`), "")
                .replace(new RegExp(`[${charList}]+$`), "");
        };

    // Check if we have a csv file given as the first parameter.
    if (!process.argv[2] || path.parse(process.argv[2]).ext !== ".csv") {
        console.log(chalk.red("No CSV file specified!"));
        process.exit();
    }

    if (!fs.existsSync(path.resolve(process.argv[2]))) {
        console.log(chalk.red("File provided doesn't exists!"));
        process.exit();
    }

    let promises = [];
    fs.createReadStream(path.resolve(process.argv[2]))
        .pipe(CSVParse())
        .on("data", chunk => {
            if (!chunk[0] || !chunk[1] || !chunk[2] || !chunk[3]) {
                console.log(chalk.red("Found an empty route, not importing"));
                console.log(JSON.stringify(chunk));
                return true;
            }

            // We have to strip the protocol if we have found it, also we strip slashes in the beginning and ending of the route.
            let resource = chunk[0]
                    .toString()
                    .toLowerCase()
                    .replace(/^(http[s]?:\/\/)/, ""),
                destination = chunk[1]
                    .toString()
                    .toLowerCase()
                    .replace(/^(http[s]?:\/\/)/, "");

            if (chunk[3] === "external") {
                destination = chunk[1];
            }

            resource = trim(resource);
            destination = trim(destination);

            promises.push(
                db.collection("routes").insertOne({
                    resource,
                    destination,
                    type: chunk[3],
                    status: parseInt(chunk[2])
                })
            );
        })
        .on("end", async () => {
            await Promise.all(promises);

            console.log(
                chalk.green(
                    `${
                        promises.length
                    } routes have been imported from the file: ${
                        process.argv[2]
                    }`
                )
            );
            connection.close();
        });
})();
