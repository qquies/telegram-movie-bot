DO $$
DECLARE
    missing_columns text[];
BEGIN
    
     -- ==================================================
    -- Проверка таблицы user
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'user_id') THEN
        missing_columns := array_append(missing_columns, 'user_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'telegram_id') THEN
        missing_columns := array_append(missing_columns, 'telegram_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'user_name') THEN
        missing_columns := array_append(missing_columns, 'user_name');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'user_surname') THEN
        missing_columns := array_append(missing_columns, 'user_surname');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'user_patronymic') THEN
        missing_columns := array_append(missing_columns, 'user_patronymic');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'rank_Id') THEN
        missing_columns := array_append(missing_columns, 'rank_Id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'date_of_сreation') THEN
        missing_columns := array_append(missing_columns, 'date_of_сreation');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'user' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице user отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица user: все колонки присутствуют';
    END IF;


END $$;