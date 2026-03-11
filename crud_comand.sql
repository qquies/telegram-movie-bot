-- =====================================================
-- CRUD операции для базы данных "Cinema_bd"
-- =====================================================

-- -----------------------------------------------------
-- Таблица director (режиссёры)
-- -----------------------------------------------------

-- CREATE: добавить нового режиссёра
INSERT INTO public.director (director_name, director_surname, director_patronymic, date_of_birth)
VALUES ('Квентин', 'Тарантино', 'Джером', '1963-03-27');

-- READ: получить всех режиссёров
SELECT * FROM public.director;

-- READ: получить конкретного режиссёра по id
SELECT * FROM public.director WHERE director_id = 1;

-- UPDATE: изменить фамилию режиссёра
UPDATE public.director
SET director_surname = 'Тарантино (обновлено)'
WHERE director_id = 1;

-- DELETE: удалить режиссёра (если нет связанных записей)
DELETE FROM public.director WHERE director_id = 1;

-- -----------------------------------------------------
-- Таблица genre (жанры)
-- -----------------------------------------------------

-- CREATE: добавить жанр
INSERT INTO public.genre (genre_title) VALUES ('Комедия');

-- READ: все жанры
SELECT * FROM public.genre;

-- UPDATE: изменить название жанра
UPDATE public.genre SET genre_title = 'Драма' WHERE genre_id = 1;

-- DELETE: удалить жанр (если не используется)
DELETE FROM public.genre WHERE genre_id = 1;

-- -----------------------------------------------------
-- Таблица rank (ранги пользователей)
-- -----------------------------------------------------

-- CREATE: добавить ранг
INSERT INTO public.rank (rank_title) VALUES ('Новичок');

-- READ: все ранги
SELECT * FROM public.rank;

-- UPDATE: изменить название ранга
UPDATE public.rank SET rank_title = 'Любитель' WHERE rank_id = 1;

-- DELETE: удалить ранг (если не используется)
DELETE FROM public.rank WHERE rank_id = 1;

-- -----------------------------------------------------
-- Таблица studio (киностудии)
-- -----------------------------------------------------

-- CREATE: добавить студию
INSERT INTO public.studio (studio_title) VALUES ('Warner Bros.');

-- READ: все студии
SELECT * FROM public.studio;

-- UPDATE: изменить название студии
UPDATE public.studio SET studio_title = '20th Century Fox' WHERE studio_id = 1;

-- DELETE: удалить студию (если нет связанных фильмов)
DELETE FROM public.studio WHERE studio_id = 1;

-- -----------------------------------------------------
-- Таблица product (фильмы)
-- -----------------------------------------------------

-- CREATE: добавить фильм
-- Необходимо предварительно иметь существующие id жанра, страны, режиссёра, студии
INSERT INTO public.product (
    product_title,
    date_release,
    user_mark_our,
    user_mark_kino_poisk,
    user_mark_imdb,
    genre_id,
    poster_link,
    country,
    director_id,
    studio_id,
    budget
)
VALUES (
    'Криминальное чтиво',
    '1994-10-14',
    9.5,
    8.9,
    8.9,
    1,           -- genre_id, например 1 = триллер
    'https://example.com/pulp_fiction.jpg',
    1,           -- country (код страны, таблица отсутствует, но поле есть)
    1,           -- director_id
    1,           -- studio_id
    8000000.00
);

-- READ: все фильмы с названиями связанных сущностей (пример JOIN)
SELECT
    p.product_id,
    p.product_title,
    p.date_release,
    p.user_mark_our,
    g.genre_title,
    d.director_name || ' ' || d.director_surname AS director,
    s.studio_title,
    p.budget
FROM public.product p
LEFT JOIN public.genre g ON p.genre_id = g.genre_id
LEFT JOIN public.director d ON p.director_id = d.director_id
LEFT JOIN public.studio s ON p.studio_id = s.studio_id;

-- UPDATE: изменить оценку фильма (например, на Кинопоиске)
UPDATE public.product
SET user_mark_kino_poisk = 9.0
WHERE product_id = 1;

-- DELETE: удалить фильм (сначала нужно удалить связанные записи, например, отзывы и связи с наградами)
DELETE FROM public.product WHERE product_id = 1;

-- -----------------------------------------------------
-- Таблица user (пользователи)
-- -----------------------------------------------------

-- CREATE: добавить пользователя
-- rank_Id должно существовать в таблице rank
INSERT INTO public."user" (
    telegram_id,
    user_name,
    user_surname,
    user_patronymic,
    "rank_Id"
)
VALUES (
    123456789,
    'Иван',
    'Иванов',
    'Иванович',
    1    -- rank_Id
);

-- READ: всех пользователей
SELECT * FROM public."user";

-- READ: пользователя по telegram_id
SELECT * FROM public."user" WHERE telegram_id = 123456789;

-- UPDATE: изменить имя пользователя
UPDATE public."user"
SET user_name = 'Пётр'
WHERE user_id = 1;

-- DELETE: удалить пользователя (сначала удалить его отзывы)
DELETE FROM public."user" WHERE user_id = 1;

-- -----------------------------------------------------
-- Таблица review (отзывы)
-- -----------------------------------------------------

-- CREATE: добавить отзыв
-- user_id и product_id должны существовать
INSERT INTO public.review (
    user_id,
    product_id,
    review_title,
    review_mark
)
VALUES (
    1,   -- user_id
    1,   -- product_id
    'Шедевр!',
    10
);

-- READ: отзывы с информацией о пользователе и фильме
SELECT
    r.review_id,
    u.user_name,
    p.product_title,
    r.review_title,
    r.review_mark,
    r.date_of_foundation
FROM public.review r
JOIN public."user" u ON r.user_id = u.user_id
JOIN public.product p ON r.product_id = p.product_id;

-- UPDATE: изменить оценку в отзыве
UPDATE public.review
SET review_mark = 9
WHERE review_id = 1;

-- DELETE: удалить отзыв
DELETE FROM public.review WHERE review_id = 1;

-- -----------------------------------------------------
-- Таблица reward (награды)
-- -----------------------------------------------------

-- CREATE: добавить награду
INSERT INTO public.reward (
    date_event,
    reward_category,
    reward_title
)
VALUES (
    '2023-05-20',
    'Оскар',
    'Лучший фильм'
);

-- READ: все награды
SELECT * FROM public.reward;

-- UPDATE: изменить название награды
UPDATE public.reward
SET reward_title = 'Лучший режиссёр'
WHERE reward_id = 1;

-- DELETE: удалить награду (если нет связей с фильмами)
DELETE FROM public.reward WHERE reward_id = 1;

-- -----------------------------------------------------
-- Таблица reward_product_conection (связь наград и фильмов)
-- -----------------------------------------------------

-- CREATE: связать фильм с наградой (например, фильм получил награду)
-- reward_id и product_id должны существовать
INSERT INTO public.reward_product_conection (
    reward_id,
    product_id,
    condition_reward
)
VALUES (
    1,   -- reward_id
    1,   -- product_id
    true   -- условие: true = получил, false = номинирован (пример)
);

-- READ: все связи с названиями наград и фильмов
SELECT
    rpc.reward_pr_id,
    rw.reward_title,
    p.product_title,
    rpc.condition_reward
FROM public.reward_product_conection rpc
JOIN public.reward rw ON rpc.reward_id = rw.reward_id
JOIN public.product p ON rpc.product_id = p.product_id;

-- UPDATE: изменить условие (например, был номинирован, а теперь получил)
UPDATE public.reward_product_conection
SET condition_reward = true
WHERE reward_pr_id = 1;

-- DELETE: удалить связь
DELETE FROM public.reward_product_conection WHERE reward_pr_id = 1;

-- -----------------------------------------------------
-- Примечания
-- -----------------------------------------------------
-- 1. Все таблицы имеют поля date_of_update и date_of_сreation/date_of_foundation,
--    которые заполняются автоматически DEFAULT now(), а date_of_update обновляется
--    триггерами при UPDATE.
-- 2. При вставке не нужно указывать поля-идентификаторы, если они создаются
--    последовательностями (DEFAULT nextval).
-- 3. В таблице product есть несколько CHECK-ограничений на оценки (0-10) и бюджет.
-- 4. Поле country в product не связано внешним ключом, поэтому можно вставлять любые числа.
-- 5. Для удаления записей, на которые есть внешние ключи, необходимо сначала удалить
--    зависимые записи или использовать каскадное удаление (не настроено).