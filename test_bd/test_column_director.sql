DO $$
DECLARE
    missing_columns text[];
BEGIN
    -- ==================================================
    -- Проверка таблицы director
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'director_id') THEN
        missing_columns := array_append(missing_columns, 'director_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'director_name') THEN
        missing_columns := array_append(missing_columns, 'director_name');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'director_surname') THEN
        missing_columns := array_append(missing_columns, 'director_surname');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'director_patronymic') THEN
        missing_columns := array_append(missing_columns, 'director_patronymic');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'date_of_birth') THEN
        missing_columns := array_append(missing_columns, 'date_of_birth');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'date_of_сreation') THEN
        missing_columns := array_append(missing_columns, 'date_of_сreation');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'date_of_formation') THEN
        missing_columns := array_append(missing_columns, 'date_of_formation');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'director' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице director отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица director: все колонки присутствуют';
    END IF;
END $$;