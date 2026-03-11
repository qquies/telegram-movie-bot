DO $$
DECLARE
    missing_columns text[];
BEGIN
    -- ==================================================
    -- Проверка таблицы reward
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward' AND column_name = 'reward_id') THEN
        missing_columns := array_append(missing_columns, 'reward_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward' AND column_name = 'date_event') THEN
        missing_columns := array_append(missing_columns, 'date_event');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward' AND column_name = 'reward_category') THEN
        missing_columns := array_append(missing_columns, 'reward_category');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward' AND column_name = 'reward_title') THEN
        missing_columns := array_append(missing_columns, 'reward_title');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward' AND column_name = 'date_of_foundation') THEN
        missing_columns := array_append(missing_columns, 'date_of_foundation');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице reward отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица reward: все колонки присутствуют';
    END IF;


END $$;