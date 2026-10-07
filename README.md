# Contao avalex Bundle

[![Packagist Version](https://img.shields.io/packagist/v/avalexgmbh/contao.svg?style=flat-square)](https://packagist.org/packages/avalexgmbh/contao)
[![License: LGPL v3](https://img.shields.io/badge/License-LGPL%20v3-blue.svg?style=flat-square)](http://www.gnu.org/licenses/lgpl-3.0)

With the avalex extension you can integrate dynamic legal texts like imprint, privacy policy and (if purchased) cancellation policy and terms and conditions into your Contao website. The texts are kept up to date automatically by avalex.

> Please note that you need an individual API key for your website, which can be purchased at [avalex.de](https://avalex.de).

---

## Requirements

- [Contao](https://github.com/contao/contao) **5.3 or newer** (including Contao 6)
- PHP 8.1 or newer

---

## Installation

Via **Contao Manager** or **Composer**:

```bash
composer require avalexgmbh/contao
```

Afterwards update the database via the Contao Manager or the `contao:migrate` command:

```bash
vendor/bin/contao-console contao:migrate
```

---

## Configuration

The texts can be embedded either as a **content element** (recommended) or as a **frontend module**. Both are available in the **avalex** category with the following types:

| Type                         | Text                                 |
|------------------------------|--------------------------------------|
| `avalex_privacy_policy`      | Privacy policy                       |
| `avalex_imprint`             | Imprint                              |
| `avalex_terms_conditions`    | Terms and conditions (if licensed)   |
| `avalex_cancellation_policy` | Cancellation policy (if licensed)    |

1. Create a new content element in an article (or a frontend module in your theme) and choose one of the types above.
2. Enter the **domain** and the matching **API key** as shown in your avalex account.
3. Save - the texts are fetched immediately and the result is shown as a message in the back end. Saving again only fetches the texts if the domain or API key changed, the last update failed or the texts are older than 6 hours.

If you are running multiple websites, simply create one element / module per domain and API key.

> In the back end, the content element only shows the date of the last update instead of the whole text.

---

## How it works

- The texts are fetched in **all languages** available for your domain and stored in the database. The frontend displays the text matching the language of the current page and falls back to German if the language is not available.
- A Contao **cron job** checks hourly and updates all texts older than 6 hours. Make sure the [Contao cron](https://docs.contao.org/manual/en/performance/cronjobs/) is set up properly.
- If the texts of a content element or module change, the corresponding entries of the **HTTP cache** are invalidated automatically.
- The back end start page shows the date of the last update of each content element and module and warns if the last update failed or the texts could not be updated for more than 24 hours. Details about failed updates can be found in the system log.

---

## Templates

The texts are rendered using Twig templates which can be customized in the template editor of the back end or within your `templates/` directory:

| Template                                              | Description                                     |
|-------------------------------------------------------|-------------------------------------------------|
| `content_element/_avalex.html.twig`                   | Shared base of all content elements             |
| `content_element/avalex_<type>.html.twig`             | Content element of the given type, e.g. `avalex_imprint` |
| `frontend_module/_avalex.html.twig`                   | Shared base of all frontend modules             |
| `frontend_module/avalex_<type>.html.twig`             | Frontend module of the given type               |

### Example

```twig
{# templates/content_element/avalex_imprint/custom.html.twig #}
{% extends '@Contao/content_element/avalex_imprint.html.twig' %}

{% block content %}
    {% if as_editor_view %}
        {{ parent() }}
    {% else %}
        <div class="legal-text">
            {{ content|insert_tag|raw }}
        </div>
    {% endif %}
{% endblock %}
```

The variant can then be selected in the element / module settings under *Custom template*.

---

## Disclaimer

The legal texts are generated and provided by [avalex GmbH](https://avalex.de). For questions regarding the content of the texts or your license, please contact avalex directly.
