DO $$
DECLARE
    missing_columns text[];
BEGIN

    -- ==================================================
    -- Проверка таблицы product
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'product_id') THEN
        missing_columns := array_append(missing_columns, 'product_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'product_title') THEN
        missing_columns := array_append(missing_columns, 'product_title');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'date_release') THEN
        missing_columns := array_append(missing_columns, 'date_release');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'user_mark_our') THEN
        missing_columns := array_append(missing_columns, 'user_mark_our');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'user_mark_kino_poisk') THEN
        missing_columns := array_append(missing_columns, 'user_mark_kino_poisk');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'user_mark_imdb') THEN
        missing_columns := array_append(missing_columns, 'user_mark_imdb');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'genre_id') THEN
        missing_columns := array_append(missing_columns, 'genre_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'poster_link') THEN
        missing_columns := array_append(missing_columns, 'poster_link');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'country') THEN
        missing_columns := array_append(missing_columns, 'country');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'director_id') THEN
        missing_columns := array_append(missing_columns, 'director_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'studio_id') THEN
        missing_columns := array_append(missing_columns, 'studio_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'budget') THEN
        missing_columns := array_append(missing_columns, 'budget');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product' AND column_name = 'date_of_foundation') THEN
        missing_columns := array_append(missing_columns, 'date_of_foundation');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице product отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица product: все колонки присутствуют';
    END IF;

END $$;