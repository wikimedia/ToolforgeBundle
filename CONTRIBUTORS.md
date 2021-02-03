Contributors
============

This file contains information for people wanting to contribute to the Toolforge Bundle.
For general usage documentation, see the [README](README.md).

## Project setup

For most development, you need to work on the bundle within an actual Symfony application; here we call this the 'host' application.
This can be set up with a command such as `symfony new ProjectName`, which will install the host application to the `ProjectName/` directory.
We suggest cloning ToolforgeBundle to an adjacent location to this (`git clone https://github.com/wikimedia/ToolforgeBundle.git`).

Then you need to set up the host application's Composer file to include the ToolforgeBundle.
Do this by first adding the following to its `composer.json` (replacing the path to `ToolforgeBundle/` to match what is correct for your setup):

    "repositories": [{
        "type": "path",
        "url": "../ToolforgeBundle/",
        "options": {
            "symlink": true
        }
    }],

Then run `composer require wikimedia/toolforge-bundle @dev`,
which will create a symlink in the host application's `vendor/wikimedia/` directory.

It is now possible to work on the files in the bundle and use them in the host application.
