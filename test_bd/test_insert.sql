DO $$
DECLARE
    v_rank_id rank.rank_id%TYPE;
    v_director_id director.director_id%TYPE;
    v_genre_id genre.genre_id%TYPE;
    v_studio_id studio.studio_id%TYPE;
    v_user_id "user".user_id%TYPE;
    v_product_id product.product_id%TYPE;
    v_reward_id reward.reward_id%TYPE;
    v_reward_pr_id reward_product_conection.reward_pr_id%TYPE;
    v_review_id review.review_id%TYPE;
BEGIN
    -- Начинаем транзакцию (в DO блоке она автоматически выполняется в одной транзакции,
    -- но мы явно не коммитим, а в конце сделаем ROLLBACK)
    
    -- ==================================================
    -- 1. Вставка в таблицу rank (независимая)
    -- ==================================================
    INSERT INTO rank (rank_title)
    VALUES ('Тестовый ранг')
    RETURNING rank_id INTO v_rank_id;
    
    IF NOT EXISTS (SELECT 1 FROM rank WHERE rank_id = v_rank_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в rank не была добавлена';
    END IF;
    RAISE NOTICE 'rank вставлен успешно, id = %', v_rank_id;

    -- ==================================================
    -- 2. Вставка в таблицу director
    -- ==================================================
    INSERT INTO director (
        director_name, director_surname, director_patronymic,
        date_of_birth, date_of_сreation, date_of_formation, date_of_update
    ) VALUES (
        'Иван', 'Петров', 'Сергеевич',
        '1980-05-15', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    ) RETURNING director_id INTO v_director_id;
    
    IF NOT EXISTS (SELECT 1 FROM director WHERE director_id = v_director_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в director не была добавлена';
    END IF;
    RAISE NOTICE 'director вставлен успешно, id = %', v_director_id;

    -- ==================================================
    -- 3. Вставка в таблицу genre
    -- ==================================================
    INSERT INTO genre (genre_title)
    VALUES ('Тестовый жанр')
    RETURNING genre_id INTO v_genre_id;
    
    IF NOT EXISTS (SELECT 1 FROM genre WHERE genre_id = v_genre_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в genre не была добавлена';
    END IF;
    RAISE NOTICE 'genre вставлен успешно, id = %', v_genre_id;

    -- ==================================================
    -- 4. Вставка в таблицу studio
    -- ==================================================
    INSERT INTO studio (studio_title, date_of_сreation, date_of_update, date_of_foundation)
    VALUES ('Тестовая студия', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    RETURNING studio_id INTO v_studio_id;
    
    IF NOT EXISTS (SELECT 1 FROM studio WHERE studio_id = v_studio_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в studio не была добавлена';
    END IF;
    RAISE NOTICE 'studio вставлена успешно, id = %', v_studio_id;

    -- ==================================================
    -- 5. Вставка в таблицу user (зависит от rank)
    -- ==================================================
    INSERT INTO "user" (
        telegram_id, user_name, user_surname, user_patronymic,
        "rank_Id", date_of_сreation, date_of_update
    ) VALUES (
        123456789, 'Алексей', 'Иванов', 'Петрович',
        v_rank_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    ) RETURNING user_id INTO v_user_id;
    
    IF NOT EXISTS (SELECT 1 FROM "user" WHERE user_id = v_user_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в user не была добавлена';
    END IF;
    RAISE NOTICE 'user вставлен успешно, id = %', v_user_id;

    -- ==================================================
    -- 6. Вставка в таблицу product (зависит от director, genre, studio)
    -- ==================================================
    INSERT INTO product (
        product_title, date_release, user_mark_our, user_mark_kino_poisk,
        user_mark_imdb, genre_id, poster_link, country, director_id, studio_id,
        budget, date_of_update, date_of_foundation
    ) VALUES (
        'Тестовый фильм', '2023-01-01', 7.5, 8.0, 7.8,
        v_genre_id, 'http://example.com/poster.jpg', 1, v_director_id, v_studio_id,
        1500000.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    ) RETURNING product_id INTO v_product_id;
    
    IF NOT EXISTS (SELECT 1 FROM product WHERE product_id = v_product_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в product не была добавлена';
    END IF;
    RAISE NOTICE 'product вставлен успешно, id = %', v_product_id;

    -- ==================================================
    -- 7. Вставка в таблицу reward
    -- ==================================================
    INSERT INTO reward (date_event, reward_category, reward_title, date_of_update, date_of_foundation)
    VALUES (CURRENT_TIMESTAMP, 'Тестовая категория', 'Тестовая награда', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    RETURNING reward_id INTO v_reward_id;
    
    IF NOT EXISTS (SELECT 1 FROM reward WHERE reward_id = v_reward_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в reward не была добавлена';
    END IF;
    RAISE NOTICE 'reward вставлен успешно, id = %', v_reward_id;

    -- ==================================================
    -- 8. Вставка в таблицу reward_product_conection (зависит от reward и product)
    -- ==================================================
    INSERT INTO reward_product_conection (
        reward_id, product_id, condition_reward, date_of_update, date_of_foundation
    ) VALUES (
        v_reward_id, v_product_id, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    ) RETURNING reward_pr_id INTO v_reward_pr_id;
    
    IF NOT EXISTS (SELECT 1 FROM reward_product_conection WHERE reward_pr_id = v_reward_pr_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в reward_product_conection не была добавлена';
    END IF;
    RAISE NOTICE 'reward_product_conection вставлена успешно, id = %', v_reward_pr_id;

    -- ==================================================
    -- 9. Вставка в таблицу review (зависит от user и product)
    -- ==================================================
    INSERT INTO review (
        user_id, product_id, review_title, review_mark,
        date_of_update, date_of_foundation
    ) VALUES (
        v_user_id, v_product_id, 'Тестовый отзыв', 8,
        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    ) RETURNING review_id INTO v_review_id;
    
    IF NOT EXISTS (SELECT 1 FROM review WHERE review_id = v_review_id) THEN
        RAISE EXCEPTION 'Ошибка: запись в review не была добавлена';
    END IF;
    RAISE NOTICE 'review вставлен успешно, id = %', v_review_id;

    -- ==================================================
    -- Если все вставки прошли успешно, сообщаем об этом
    -- ==================================================
    RAISE NOTICE 'Все тестовые вставки выполнены успешно. Данные будут откачены.';

    -- Откатываем транзакцию, чтобы не загрязнять базу
    ROLLBACK;
    
    -- Если мы дошли до сюда, значит все исключения не сработали, тест пройден
    RAISE NOTICE 'Тест вставки завершён успешно (транзакция отменена).';
    
EXCEPTION
    WHEN OTHERS THEN
        -- В случае любой ошибки откатываем транзакцию и выдаём сообщение
        ROLLBACK;
        RAISE NOTICE 'Тест вставки не пройден: %', SQLERRM;
        RAISE;
END $$;