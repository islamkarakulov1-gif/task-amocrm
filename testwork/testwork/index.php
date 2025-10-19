<?php
require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', 'karakulovislam5');
define('CLIENT_ID', '888633b1-4489-40eb-81b1-c7741af2ce54');
define('CLIENT_SECRET', '0i6p6u2zdFCMBp0pdZgu8j9qGlwga2UbfC28OH5vQ0lDGdFWkxlxCDYAr12cNOqP');
define('CODE', 'def5020083afc389e6ced1dce2c4513a4f798d710133a7964ddf9530fbabf60164269bf51c477a4b7fdbe0184d16bff5d467fd6c30e66c29cf1af7ce45a753ea325da0dea04c1c326524f1c677d6e009d58ba2b7f6da7a2b4d3312c7e5f541d4de3f53ea8a9e8f1b2a616311c2016fa8c851ce596e2b995c43c23f5b325d48714f62c53287fe8cfb7b3d684c885b3b9ca0b681da1dcfa7416e8b7ce880b449584d61399c1dca9a13c000cbd14db3053ffae76106c3fff8e0a657607ca1ddebaed8200e97368d0b20cb27e3a4911b660c7b8e2e76b3a1ddbb9a9afba4c834a48d2bd7d7a1899eaa35cddcd76026c44c00ddffa0896f9253849e4842678a736ca33efc16d6ab45b9ca5ca339faf89e60c84281528c242377ba377141079748f9306125fc314425f51f3efb9ef9406b73bef903ab1758a3cc5caaf4efaa712fdd3c171bad969d1cd6e19e5d7a401b1fecc9c341481b1c6458f056b44fe5020a303128fdc89f9e4d1bd85d3f3990b76b4855c40f7d8f45ca6848ab9c22ba095a6503cd2651061907804340c38d6b6b7ab0c949e7549a5156c84f23e69835573b12c2a61f4806d48b6f6fc53a0ef392b667fc23c78db374dd792009b6ca3af318ed24995132e735069252355a095f135bc2176b578fb78b63d815827e57356d8d52db4023f326f427a3921ed3e83e3052cd0e2727');
define('REDIRECT_URL', 'https://karakulovislam5.amocrm.ru');

echo "<pre>";

try {
    $amoV4Client = new AmoCrmV4Client(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
    $pipelineId = 10204694;           // ID воронки "Воронка"
    $applicationStatusId = 80797202;  // ID этапа "Заявка" 
    $waitingStatusId = 80797206;      // ID этапа "Ожидание клиента"
    $confirmedStatusId = 80797210;    // ID этапа "Клиент подтвердил"
    //Перемещение сделок с бюджетом > 5000
    echo "=== ПУНКТ 2: Перемещение сделок с бюджетом > 5000 ===\n";
    //Ищем сделки на этапе "Заявка"
    $leadsApplication = $amoV4Client->GETAll("leads", [
        "filter[statuses][0][pipeline_id]" => $pipelineId,
        "filter[statuses][0][status_id]" => $applicationStatusId
    ]);
    echo "Найдено сделок на этапе 'Заявка': " . count($leadsApplication) . "\n";
    $movedCount = 0;
    foreach ($leadsApplication as $lead) {
        $budget = $lead['price'] ?? 0;
        //Проверяем бюджет сделки
        if ($budget > 5000) {
            echo "Сделка ID: {$lead['id']}, Бюджет: {$budget} - перемещаем на 'Ожидание клиента'\n";
            
            //Обновляем статус сделки точнее перемещаем
            $updateData = [
                "id" => $lead['id'],
                "status_id" => $waitingStatusId
            ];
            $result = $amoV4Client->POSTRequestApi("leads/{$lead['id']}", $updateData, "PATCH");
            if ($result) {
                $movedCount++;
                echo "Сделка {$lead['id']} успешно перемещена\n";
            } else {
                echo "Ошибка перемещения сделки {$lead['id']}\n";
            }
        }
    }
    echo "Итого перемещено сделок: {$movedCount}\n\n";
    //Копирование сделок с бюджетом 4999
    echo "=== ПУНКТ 3: Копирование сделок с бюджетом 4999 ===\n";
    //Ищем сделки на этапе "Клиент подтвердил" с бюджетом 4999
    $leadsConfirmed = $amoV4Client->GETAll("leads", [
        "filter[statuses][0][pipeline_id]" => $pipelineId,
        "filter[statuses][0][status_id]" => $confirmedStatusId,
        "filter[price]" => 4999
    ]);
    echo "Найдено сделок для копирования: " . count($leadsConfirmed) . "\n";
    $copiedCount = 0;
    foreach ($leadsConfirmed as $lead) {
        echo "Копируем сделку ID: {$lead['id']} '{$lead['name']}'\n";
        echo "Бюджет оригинала: {$lead['price']}\n";
        //СОЗДАЕМ КОПИЮ сделки 
        $newLeadData = [
            "name" => "Копия: " . $lead['name'],
            "price" => $lead['price'],
            "status_id" => $waitingStatusId, //Новая сделка на Ожидание клиента
            "pipeline_id" => $pipelineId,
            "custom_fields_values" => $lead['custom_fields_values'] ?? []
        ];
        $newLead = $amoV4Client->POSTRequestApi("leads", [$newLeadData]);
        if ($newLead && isset($newLead[0]['id'])) {
            $newLeadId = $newLead[0]['id'];
            echo "✅ Новая сделка создана с ID: {$newLeadId}\n";
            echo "Оригинал ID: {$lead['id']} остается на этапе 'Клиент подтвердил'\n";
            
            //Копируем примечания
            $notes = $amoV4Client->GETAll("leads/{$lead['id']}/notes");
            $notesCount = 0;
            foreach ($notes as $note) {
                $noteData = [
                    "entity_id" => $newLeadId, //Прикрепляем к НОВОЙ сделке
                    "note_type" => $note['note_type'],
                    "params" => $note['params'] ?? []
                ];
                $amoV4Client->POSTRequestApi("leads/notes", [$noteData]);
                $notesCount++;
            }
            echo "   Примечания скопированы: {$notesCount} штук\n";
            
            //Копируем задачи
            $tasks = $amoV4Client->GETAll("tasks", [
                "filter[entity_id]" => $lead['id'],
                "filter[entity_type]" => "leads"
            ]);
            $tasksCount = 0;
            foreach ($tasks as $task) {
                $taskData = [
                    "task_type_id" => $task['task_type_id'],
                    "text" => $task['text'],
                    "complete_till" => $task['complete_till'],
                    "entity_id" => $newLeadId, //Прикрепляем к НОВОЙ сделке
                    "entity_type" => "leads",
                    "responsible_user_id" => $task['responsible_user_id']
                ];
                $amoV4Client->POSTRequestApi("tasks", [$taskData]);
                $tasksCount++;
            }
            echo "   Задачи скопированы: {$tasksCount} штук\n";
            $copiedCount++;
            echo "✅ Сделка успешно скопирована!\n";
        } else {
            echo "❌ Ошибка копирования сделки {$lead['id']}\n";
        }
        echo "---\n";
    }
    echo "Итого скопировано сделок: {$copiedCount}\n";
    //Финальная проверка
    echo "\n=== РЕЗУЛЬТАТ ===\n";
    echo "После выполнения:\n";
    echo "- На 'Ожидание клиента' должны быть: Т6000 (перемещенная) + Копия Т4999\n";
    echo "- На 'Клиент подтвердил' должны быть: Т5000 + Оригинал Т4999\n";
    
}
catch (Exception $ex) {
    var_dump($ex);
    file_put_contents("ERROR_LOG.txt", 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки:' . $ex->getCode());
}