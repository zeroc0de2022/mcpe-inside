# Проект "Парсер mcpe-inside + RestApi"

## Описание

Проект представляет собой веб-сервис с парсером данных для сбора и хранения информации с указанного веб-сайта в базе данных. 
Также реализован REST API для получения данных в мобильном приложении.

## Функциональности

### Парсер

Проект содержит парсер, который осуществляет сбор данных с [веб-сайта](https://mcpe-inside.ru) в базу данных. 
Парсер обрабатывает заголовок, описание, изображения, файлы, и другие параметры. 
Данные записываются в базу данных, а файлы и изображения загружаются на сервер, с ссылками на них в базе данных. 
Парсер также автоматически пополняет базу данных по расписанию, собирая новые записи с того же источника.

### Google Translate API

Заголовок и описание собранных данных переводятся на 23 различные языки с использованием Google Translate API. 
Поддерживаемые языки: 
- Португальский(pt)
- Русский(ru)
- Испанский(es)
- Турецкий(tr)
- Индонезийский(id)
- Английский(en)
- Вьетнамский(vi)
- Итальянский(it)
- Польский(pl)
- Французский(fr)
- Немецкий(de)
- Румынский(ro)
- Украинский(uk)
- Чешский(cs)
- Венгерский(hu)
- Малайский(ms)
- Греческий(el)
- Болгарский(bg)
- Словацкий(sk)
- Литовский(lt)
- Нидерландский(nl)
- Хорватский(hr)
- Сербский(sr)
  
Переведенные данные также сохраняются в базе данных.



### REST API

Разработан REST API с аутентификацией для получения данных в формате JSON и отправки обновлений на сервер через мобильное приложение. 
Реализованы следующие запросы:

#### GET запросы

- Получение n следующих записей (по категории, подкатегории, версии) с сортировкой по просмотрам, загрузкам, дате добавления, лайкам.
- Получение записи со всеми данными с параметром lang (заголовок, описание, ссылки на изображения, ссылки на файлы, просмотры, загрузки и т.д.).
- примеры запросов в файле **/config/queries**

#### UPDATE запросы

- Инкремент и декремент значений полей likes, downloads, views.


## Установка

1. Склонируйте репозиторий.
2. Установить таблицы в БД из **/sql/mcpe-inside.sql**
3. Прописать данные БД в **/config/db_params.php**
4. Повесить на крон 3 файла из **Parser** (**category.php**, **items.php**, **skins.php**)
5. При необходимости добавить список рабочих прокси в файле и указать адрес файла в настройках (**Parser/settings.php**). Прокси могут пригодится чтобы обойти ограничение по запросам от Google Translate API
6. Пользоваться
