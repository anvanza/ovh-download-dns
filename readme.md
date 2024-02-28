# Download all DNS zones of OVH in PHP

Go to https://eu.api.ovh.com/createApp/ and create an app
- Copy the config.yml.dist to config.yml
- Fill in API_KEY and API_SECRET
- Remove the CONSUMER KEY

Run the export.php and go to the url.
If the export.php runs again, the backup is generated

Thanks to https://github.com/Jolg42/ovh-export-dns for the inspiration