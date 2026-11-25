# IndexNow bundle for Contao

[Deutsch](README.de.md)

Automatically reports new, changed and removed pages, news, events and FAQs to [IndexNow](https://www.indexnow.org/). The reports reach Bing, Yandex, Seznam, Naver and Yep. These search engines share the reports with each other, so a single endpoint is enough. Google does not take part in IndexNow.

## Requirements

- Contao 6.0 or later (6.x)
- PHP 8.4 or later
- For news, events and FAQs: the respective Contao bundle (optional)
- A Contao cron job that runs regularly (see [Cron job](#cron-job))

## Installation

Search for `klausmedia/contao-indexnow-bundle` in the Contao Manager, install the package and update the database.

Or on the command line:

```bash
composer require klausmedia/contao-indexnow-bundle
vendor/bin/contao-console contao:migrate
```

The database update creates the following:

| Name | Purpose |
| --- | --- |
| `tl_page.indexnowEnable`, `tl_page.indexnowKey`, `tl_page.indexnowAutoSubmit` | Settings in the website root |
| `tl_km_indexnow_queue` | Queue of the URLs to be reported |
| `tl_km_indexnow_state` | Last runs of the cron jobs |

## Setup

1. Open the website root in the back end. In the **IndexNow** section, enable **Report changes to IndexNow**.
2. Leave the field **IndexNow key** empty and save: the bundle then generates a key with 32 characters. Your own key must be 8–128 characters long and may only contain `a–z`, `A–Z`, `0–9` and `-`.
3. Enter a domain in the website root. Without it, the cron job cannot generate valid URLs on the command line.
4. Check that `https://<domain>/<key>.txt` shows the key in the browser.
5. Optional: report all existing URLs once, with **Queue all URLs** in **System → IndexNow** or with `contao:indexnow:submit --all` on the command line. This is only necessary for websites that are new or poorly indexed. Bing finds pages it already knows anyway.

If you have several websites, repeat these steps for each website root that should report. Each website has its own key. If several website roots share a domain, e.g. one per language, one with IndexNow is enough: all URLs of this domain are then reported.

**Websites without HTTPS** are reported in the same way: IndexNow accepts http and https URLs. The protocol setting in the website root determines whether the URLs start with `http://` or `https://`. The key file is available under both protocols. The setting must match the website. If the server redirects http to https while the website root is still set to http, the bundle reports http URLs and the search engine may not find the key file.

**Changing the key:** The key can be changed in the website root at any time. Empty the field and save to generate a new one. The new key file is available immediately, and queued URLs are reported with the new key. Until the search engine has verified it, the response may be HTTP 202. Reports accepted with HTTP 202 shortly before the change may be lost. Changing the key is only necessary if you want to replace it.

## What is reported

### Reported records

Pages, news, events and FAQs with their own detail page are reported.

Not reported are:

- protected content, also if the protection is inherited from a parent page, the archive or the reader page
- content with `noindex` in its robots setting or in that of the reader page
- news, events and FAQs whose reader page is not published
- pages that are set to never appear in the sitemap
- page types without their own content: website root, folder, redirects, logout, error pages and news feeds
- news and events that only link to a page, an article or an external URL
- pages that only output news, events or FAQs through a module, when such a record changes. They can be reported by saving the page.
- websites whose root does not have IndexNow enabled or is not published. A website root without domain only reports for domains that have no website root of their own.

### Triggers

| Action in the back end | Reported URLs |
| --- | --- |
| Saving a published record | URL of the record |
| Publishing and unpublishing, also with the eye icon | URL of the record. When unpublishing, the search engine learns that it is gone. |
| New alias | Old and new URL |
| New alias of a reader page | Also the old and new URLs of all news, events and FAQs that use this page as reader page |
| Deleting | URL of the record. For pages also all subpages, for archives, calendars and FAQ categories all their entries. |
| Moving, also several at once | URLs before and after moving. For pages also the subpages. |
| Copying | The copy, as soon as it is visible. Contao creates copies of pages, articles and news unpublished. |
| Restoring from the recycle bin | All restored records, including those deleted along with them |
| Restoring an older version | URL before and after restoring |
| Start or stop date reached | URL of the record, with the next run of the cron job |

If an **article** or a **content element** changes, the page, news or event it belongs to is reported. This applies to all actions in the table, also for nested elements. The page, news or event itself must be published.

If the alias of a page changes, the URLs of its subpages do not change. Contao stores the full path in the alias of each subpage. The subpages are therefore not reported.

## Back end: System → IndexNow

The list shows the queue: each URL that still has to be reported, with the time of the change. You can filter it by website, search for URLs and delete one or several entries.

| Button | Effect |
| --- | --- |
| **Send now** | Sends the whole queue to IndexNow right away, also of websites without automatic sending. The result is shown as a message. |
| **Queue all URLs** | Reads `/sitemap.xml` of each website with IndexNow and adds all URLs to the queue. For a website root without domain, the domain of the back end is used. |

Users who are not administrators need the **IndexNow** module in their user group.

### Send automatically

**Send automatically** is enabled by default in the website root. The cron job then sends the queue every 5 minutes. If the option is off, the bundle keeps collecting URLs but only sends them with **Send now**. This is useful for staging websites, for testing and on servers without a cron job.

### Maintenance mode

While maintenance mode is active in the website root, the bundle keeps collecting URLs but does not send them, not even with **Send now** or on the command line. Search engines would otherwise only get the maintenance page (HTTP 503). Once maintenance mode has ended, the next run of the cron job sends the URLs. Without **Send automatically**, click **Send now**. The key file remains available.

In the system-wide maintenance mode of Contao, the cron job does not run and the URLs stay in the queue. Do not use the console command then, because the key file is not available.

### Notices in the list

| Notice | Meaning |
| --- | --- |
| Automatic sending is switched off for … | These websites only report with **Send now**. |
| Maintenance mode is active for … | These websites only report once maintenance mode has ended. |
| The cron job … has not run yet | Normal right after the installation. If the notice stays, no cron job runs on this server. |
| The cron job … has not run since … | The last run was more than an hour ago. |

The two cron job notices only appear while at least one website is set to send automatically. If you work without a cron job, switch off **Send automatically** and they disappear.

## How it works

1. An action in the back end adds the URL to the queue (`tl_km_indexnow_queue`). Several changes of the same URL result in a single entry.
2. The cron job sends the queue to `https://api.indexnow.org/indexnow` every 5 minutes, except for websites with **Send automatically** switched off. It bundles the URLs per website, with a maximum of 10,000 per request.
3. The response determines what happens to the URLs:

| Response | Result |
| --- | --- |
| 200, 202 | Reported, the URLs are removed from the queue |
| 429, 5xx, no connection | New attempt with the next run |
| 400, 403, 422 | Rejected, the URLs are removed and an error is written to the system log |

All messages are written to the Contao system log and start with "IndexNow:".

## Cron job

The bundle comes with two Contao cron jobs that run every 5 minutes:

- `SubmitQueueCron` sends the queue and remembers the time, so that the back end can tell whether it runs.
- `ScheduledContentCron` queues records whose start or stop date has been reached since the last run. The very first run only looks back one hour.

For both to run reliably, the server should call the Contao cron job every minute. Example:

```bash
* * * * * /usr/bin/php /path/to/project/vendor/bin/contao-console contao:cron
```

Adjust the paths to PHP and to the Contao installation to your server. The PHP binary must be version 8.4 or later.

Without a server cron job, the Contao cron only runs when the front end is visited. Reports may then be delayed.

### Hosting without a cron job

The bundle also works without a cron job. Switch off **Send automatically** in the website root and report the collected URLs from time to time with **Send now** in **System → IndexNow**. The list shows how long a URL has been waiting.

Two things are missing without a cron job:

- Start and stop dates are not detected. The record is only reported when it is saved again.
- Nothing is sent automatically. The list in the back end points this out, see [Notices in the list](#notices-in-the-list).

## Console command

```bash
vendor/bin/contao-console contao:indexnow:submit [--all] [--host=<domain>]
```

| Option | Effect |
| --- | --- |
| *(none)* | Sends the queue right away, also of websites without automatic sending. Same as **Send now** in the back end. |
| `--all` | Same as **Queue all URLs**, followed by sending: first reads `/sitemap.xml` of each website with IndexNow and queues all URLs. A sitemap index is followed one level deep. |
| `--host` | With `--all`: only this domain. Required for a website root without domain. |

The command ends with an error code if IndexNow has rejected URLs.

## Checking that it works

1. `https://<domain>/<key>.txt` returns the key as text.
2. After saving a published news item, its URL is listed in **System → IndexNow**.
3. After the next run of the cron job, the system log says "IndexNow: … URL(s) of … reported (HTTP 200)" or "(HTTP 202)".
4. The [Bing Webmaster Tools](https://www.bing.com/webmasters) list the reported URLs under **IndexNow**, often on the same day. The website must be verified there.

## Troubleshooting

| Problem | Cause and solution |
| --- | --- |
| Key file returns 404 | Check that IndexNow is enabled in the website root, the website root is published, the key is saved and the domain is correct. The key is case-sensitive. |
| HTTP 403 in the system log | The key does not match the key file, or the file cannot be reached from outside, e.g. because of password protection. The protocol in the website root may also not match the website. |
| HTTP 422 in the system log | The URLs do not belong to the domain of the key. Check the domain in the website root. |
| Scheduled content is not reported | The Contao cron job does not run regularly, or the website root has no domain. |
| The queue is not emptied | The cron job does not run, or **Send automatically** is off in the website root. Check the notices in the list and click **Send now** as a test. |

## Limitations

- The route `/{key}.txt` applies to every address of this form with 8–128 characters and responds with 404 if no key matches. Real files in `public/` take precedence. A Contao page with the URL suffix `.txt`, however, would be intercepted.
- If a stop date is set directly to a time in the past, the removal may not be reported. Unpublish the record instead.
- If several website roots share a domain, the maintenance mode of the root whose key is used applies.
- If an unpublished article is copied with its content, the bundle reports the page once unnecessarily. This does no harm.
- If a parent page is protected later, its subpages are not reported as removed.
- If an archive has a great many entries, saving its reader page after an alias change takes longer, because each URL is generated individually.
- Start and stop dates are reported with a delay of up to about 10 minutes, because the cron job runs every 5 minutes.

## Reporting issues

Please use [GitHub Issues](https://github.com/klausmedia/contao-indexnow-bundle/issues).

## License

[MIT](LICENSE) © Benny Klaus

The software is provided free of charge and without warranty, see the [license](LICENSE) for details. Back up your files and database before installing or updating.
