DO $$
DECLARE
    missing_columns text[];
BEGIN
    -- ==================================================
    -- Проверка таблицы review
    -- ==================================================
    missing_columns := ARRAY[]::text[];
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'review_id') THEN
        missing_columns := array_append(missing_columns, 'review_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'user_id') THEN
        missing_columns := array_append(missing_columns, 'user_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'product_id') THEN
        missing_columns := array_append(missing_columns, 'product_id');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'review_title') THEN
        missing_columns := array_append(missing_columns, 'review_title');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'review_mark') THEN
        missing_columns := array_append(missing_columns, 'review_mark');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'date_of_update') THEN
        missing_columns := array_append(missing_columns, 'date_of_update');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'review' AND column_name = 'date_of_foundation') THEN
        missing_columns := array_append(missing_columns, 'date_of_foundation');
    END IF;

    IF array_length(missing_columns, 1) > 0 THEN
        RAISE EXCEPTION 'В таблице review отсутствуют колонки: %', array_to_string(missing_columns, ', ');
    ELSE
        RAISE NOTICE 'Таблица review: все колонки присутствуют';
    END IF;

END $$;