DO $$
BEGIN
    -- Проверяем, существует ли таблица user
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'user') THEN
        RAISE EXCEPTION 'Таблица users не найдена';
    END IF;
	
	-- Проверяем, существует ли таблица director
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'director') THEN
        RAISE EXCEPTION 'Таблица director не найдена';
    END IF;

	-- Проверяем, существует ли таблица genre
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'genre') THEN
        RAISE EXCEPTION 'Таблица genre не найдена';
    END IF;

	-- Проверяем, существует ли таблица product
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'product') THEN
        RAISE EXCEPTION 'Таблица product не найдена';
    END IF;

	-- Проверяем, существует ли таблица rank
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'rank') THEN
        RAISE EXCEPTION 'Таблица rank не найдена';
    END IF;

	-- Проверяем, существует ли таблица review
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'review') THEN
        RAISE EXCEPTION 'Таблица review не найдена';
    END IF;

	-- Проверяем, существует ли таблица reward
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'reward') THEN
        RAISE EXCEPTION 'Таблица reward не найдена';
    END IF;

	-- Проверяем, существует ли таблица reward_product_conection
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'reward_product_conection') THEN
        RAISE EXCEPTION 'Таблица reward_product_conection не найдена';
    END IF;

	-- Проверяем, существует ли таблица studio
	IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'studio') THEN
        RAISE EXCEPTION 'Таблица studio не найдена';
    END IF;
	
    RAISE NOTICE 'Все проверки структуры пройдены';
END $$;
