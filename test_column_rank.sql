DO $$
DECLARE
    missing_columns text[];
BEGIN
    -- ==================================================
    -- Проверка таблицы rank
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'rank' AND column_name = 'rank_id') THEN
        missing_columns := array_append(missing_columns, 'rank_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'rank' AND column_name = 'rank_title') THEN
        missing_columns := array_append(missing_columns, 'rank_title');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице rank отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица rank: все колонки присутствуют';
    END IF;

END $$;