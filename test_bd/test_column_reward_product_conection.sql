DO $$
DECLARE
    missing_columns text[];
BEGIN
    -- ==================================================
    -- Проверка таблицы reward_product_conection
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward_product_conection' AND column_name = 'reward_pr_id') THEN
        missing_columns := array_append(missing_columns, 'reward_pr_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward_product_conection' AND column_name = 'reward_id') THEN
        missing_columns := array_append(missing_columns, 'reward_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward_product_conection' AND column_name = 'product_id') THEN
        missing_columns := array_append(missing_columns, 'product_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward_product_conection' AND column_name = 'condition_reward') THEN
        missing_columns := array_append(missing_columns, 'condition_reward');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward_product_conection' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reward_product_conection' AND column_name = 'date_of_foundation') THEN
        missing_columns := array_append(missing_columns, 'date_of_foundation');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице reward_product_conection отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица reward_product_conection: все колонки присутствуют';
    END IF;


END $$;