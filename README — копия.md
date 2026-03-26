## Базовый 

Проект содержит:
* Docker Compose файлы для разработки
* Настроенный контейнер PHP версии 8.3 включая Composer, Xdebug
* Настроенный контейнер NGINX

Параметры точки входа:
* Папка для проекта `src`
* Корневая директория Nginx `public`
* Точка входа `index.php` 

1. Клонировать проект
    ```Bash
    git clone https://gitverse.ru/Bfrees/Stand_for_korrektors
    ```

2. Перейти в папку с проектом
    ```Bash
    cd ~/BStand_for_korrektors
    ```
3. Выполнить билд проекта
    ```Bash
    docker compose -f dev.yml --profile client up -d --build
    ```
4. [Проверить демо-страницу в браузе](http://localhost)


