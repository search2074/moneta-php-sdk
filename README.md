# Moneta-PHP-SDK

This lib provides methods to accept payments via PayAnyWay (Moneta.ru).

New update includes support of online kassa 54-FZ. Please add a new file kassa_settings.ini under config folder.

kassa_settings.ini content is:

monetasdk_kassa_enabled = "0"
monetasdk_kassa_type = "payanyway"
monetasdk_kassa_inn = ""
monetasdk_kassa_address = ""
monetasdk_kassa_module_api_url = "https://service.modulpos.ru/api/fn"
monetasdk_kassa_module_uuid = ""
monetasdk_kassa_module_login = ""
monetasdk_kassa_module_password = ""
monetasdk_kassa_atol_api_url = "https://online.atol.ru/possystem"
monetasdk_kassa_atol_api_version = "v3"
monetasdk_kassa_atol_login = ""
monetasdk_kassa_atol_password = ""
monetasdk_kassa_atol_group_code = ""