# Moneta-PHP-SDK

This lib provides methods to accept payments via PayAnyWay (Moneta.ru).

New update includes support of online kassa 54-FZ atol V4.

kassa_settings.ini content is:
```
monetasdk_kassa_enabled = "1"
monetasdk_kassa_type = "atolonline"
monetasdk_kassa_inn = ""
monetasdk_kassa_address = "http://your_site_url.com"
monetasdk_kassa_company_email = "(required)"
monetasdk_kassa_sno_system = "osn"
monetasdk_kassa_vat_type = "vat20"
monetasdk_kassa_payment_method = "full_payment"
monetasdk_kassa_payment_object = "payment"
monetasdk_kassa_atol_api_url = "https://online.atol.ru/possystem"
monetasdk_kassa_atol_api_version = "v4"
monetasdk_kassa_atol_login = "(required)"
monetasdk_kassa_atol_password = "(required)"
monetasdk_kassa_atol_group_code = "(required)"
```

See all atol v4 props [here](https://online.atol.ru/files/API_FFD_1-0-5.pdf).