DO $$
DECLARE
    missing_columns text[];
BEGIN

    -- ==================================================
    -- Проверка таблицы genre
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'genre' AND column_name = 'genre_id') THEN
        missing_columns := array_append(missing_columns, 'genre_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'genre' AND column_name = 'genre_title') THEN
        missing_columns := array_append(missing_columns, 'genre_title');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице genre отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица genre: все колонки присутствуют';
    END IF;
END $$;