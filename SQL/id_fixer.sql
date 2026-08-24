SET @new_id = 0;
UPDATE `order_status_history` 
SET `id` = (@new_id := @new_id + 1) 
ORDER BY `id` ASC;

UPDATE table_name SET column_name = 'new value';

DELETE FROM order_status_history 
WHERE delivery_number = 'your_delivery_number_here';