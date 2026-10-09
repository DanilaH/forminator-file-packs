# Docker / VPS staging

This is a dedicated synthetic testing site, separate from existing sites. Initial real VPS installation, targeted exports and repeat deployment passed in [run 37980718219](https://github.com/DanilaH/forminator-file-packs/actions/runs/37980718219). DNS, Caddy route and HTTPS remain unverified. Extended hosting checks are recorded separately by subsequent runs.

## Workflows

- **Verify Docker staging** runs automatically on push/PR. It builds the WordPress image, installs the real plugin ZIP, checks real HTTP uploads/downloads, authorization/path handling, resource-failure behavior, Chromium/mobile UI, structured JSON and source integrity. The expanded suite adds six frontend repeater modes, the combined 100-entry/500-file/100-MiB ceiling, 8-MiB escaped JSON metadata, existing-session permission revocation, real child-process shutdown/fatal/SIGKILL recovery, uninstall/reinstall and Russian UI states. It repeats deployment and tests against the same persistent volumes to catch fixture accumulation/reinstall issues.
- **VPS staging → inspect** is a read-only SSH probe (manual, or when its workflow is updated): Docker/Compose versions, container names/images/ports, network names, disk/memory, listening ports and Python/Caddy executable availability. It never prints container environment variables or existing configuration files.
- **VPS staging → deploy** runs after a successful main-branch Docker check, or manually. It waits for the native matrix, rejects stale main commits, and repeats deployment and tests on the same volumes. It accepts only a main commit whose latest native matrix and Docker checks succeeded. It uploads code into `$HOME/ffp-staging`, builds/installs the dedicated stack and runs the targeted hosting suite. Existing destinations/stacks/volumes without ownership markers are rejected. There is no automatic Caddy edit, production deployment, global Docker pruning or volume deletion.

VPS deploy uses SSH/SFTP-style access through Actions; the assistant never needs to read the stored private key. SSH fingerprints must be pinned. Private staging deployment is automatic after both exact-commit checks succeed. Connecting a public subdomain/Caddy route is a separate setup step; automatic deployment does not edit Caddy.

## GitHub repository secrets

| Secret | Value |
|---|---|
| `FFP_SSH_HOST` | VPS IP or SSH hostname |
| `FFP_SSH_PORT` | SSH port; empty defaults to 22 |
| `FFP_SSH_USER` | User able to run Docker Compose and Python 3 |
| `FFP_SSH_PRIVATE_KEY` | SSH private key whose public key is authorized for that user |
| `FFP_SSH_KNOWN_HOSTS` | Verified OpenSSH known_hosts entry matching the host and port |

Obtain the host key through the VPS provider's trusted console or a previously verified SSH connection. A blind `ssh-keyscan` from CI is not trust verification. Do not disable StrictHostKeyChecking. A Docker-capable SSH user has broad host permissions; use a dedicated key/account for this task where practical.

## Initial private access

Leave `FFP_STAGING_URL` and `FFP_CADDY_NETWORK` repository variables unset initially. WordPress binds **only** `127.0.0.1:18080`; MariaDB has no published port. Actions uses an SSH tunnel to test the site. Port 18080 must be free. The Compose project `ffp-staging` uses its own network and named volumes, and CPU/memory limits (database 512 MiB / 0.5 CPU; web 768 MiB / 1 CPU). Check free resources before first deployment. No other Docker project is restarted.

Database/root/admin passwords are generated on the VPS and retained in `.staging-state`, which is ignored by Git. The container reads database passwords through Compose secret files. The admin account is `lab`; the password is read privately by the runner for browser testing and is never included in artifacts. To open the private site yourself:

```sh
ssh -L 18080:127.0.0.1:18080 USER@HOST
# Open http://127.0.0.1:18080 in your browser.
```

`blog_public=0` discourages search indexing. Mail is suppressed. Do not enter real submissions: this is a test installation with test fixtures. Tests remove only forms explicitly marked as created by the suite. Interrupted tests retain their fixture so a subsequent run stops for inspection rather than guessing what to delete.

## Caddy on the host

After choosing the real subdomain and pointing its DNS at the VPS, add a site block to the existing Caddy configuration:

```caddyfile
wp-test.example.com {
    header X-Robots-Tag "noindex, nofollow, noarchive"
    reverse_proxy 127.0.0.1:18080
}
```

Validate the complete existing configuration and reload it gracefully. Do not replace the existing Caddyfile. Set GitHub variable `FFP_STAGING_URL=https://wp-test.example.com` and deploy again; WordPress home/site URLs will be updated and hosting tests will use that HTTPS route.

## Caddy in Docker

Identify the existing Caddy network during inspection. Set `FFP_CADDY_NETWORK` to that exact external network and `FFP_STAGING_URL` to the chosen HTTPS origin. The optional Compose overlay attaches only WordPress to that network with alias `ffp-staging-wordpress`; the database stays on its own backend network. The site block uses:

```caddyfile
wp-test.example.com {
    header X-Robots-Tag "noindex, nofollow, noarchive"
    reverse_proxy ffp-staging-wordpress:80
}
```

The actual Caddy container, configuration mount, network and reload command must be inspected first. The supplied overlay does not create/replace the existing proxy network. Neither Caddy path is considered verified merely because the snippet exists.

## Boundaries

Official WordPress 7.1.3/PHP 8.3 Apache image with a checksum-verified WP-CLI 2.12.0; MariaDB 10.11. Image patch rebuilds can change, so evidence records runtime versions. This covers container hosting; it does not certify a shared-hosting PHP configuration, Windows, offload storage or screen-reader behavior. Exporter source files are not changed by this infrastructure. The installable plugin ZIP excludes all deployment/test code.

## Extended test boundaries

Large synthetic ZIPs stream through SSH, outside PHP JSON/base64 memory. All extra forms/pages carry staging ownership markers and are removed by the fixture cleanup. The shutdown check kills only its own PHP child export process; it does not restart Docker, exhaust host memory/disk, or crash the VPS. Historical-version upgrades remain covered by native CI. Chromium checks cover sampled accessible names, text contrast, focus, live regions and reflow; a complete accessibility audit and screen readers remain unverified.
