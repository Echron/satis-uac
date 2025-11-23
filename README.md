# Private Packagist with multi-user access

In the pub folder we generate a folder structure with .htaccess restrictions., the routing of an authenticated user
happens based on the username of that user

## Installation

- Make sure your server has htaccess support
- Add packages to satis.packages.json
- Configure the composer access to your private repository (preferably with a SSH key)
- Configure the desired endpoint(s) in generate.php
- Make sure your web server serves the pub folder
- Run generate.php

## Endpoints

You can have one or more endpoints.
For each endpoint you can define access to all or a selection of the packages defined in your configuration file
You can also define a separate satis.json for each endpoint
You can add a public (no authentication required) endpoint when you add an endpoint with the name "public"

#Multiple users
Allow users to have access to endpoints,

## TODO:

- Since we base our routing on the username, the username should be unique across the environment.
- Add tests
- Make this installable as a composer project
- Test integration with other Satis plugins

## Scripts

Purge packages

```bash
php vendor/composer/satis/bin/satis purge
```
