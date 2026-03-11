DO $$
DECLARE
    missing_columns text[];
BEGIN
    
    -- ==================================================
    -- Проверка таблицы studio
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'studio' AND column_name = 'studio_id') THEN
        missing_columns := array_append(missing_columns, 'studio_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'studio' AND column_name = 'studio_title') THEN
        missing_columns := array_append(missing_columns, 'studio_title');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'studio' AND column_name = 'date_of_сreation') THEN
        missing_columns := array_append(missing_columns, 'date_of_сreation');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'studio' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'studio' AND column_name = 'date_of_foundation') THEN
        missing_columns := array_append(missing_columns, 'date_of_foundation');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице studio отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица studio: все колонки присутствуют';
    END IF;



END $$;