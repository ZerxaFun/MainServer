<?php

APIRoute::api('post', 'api/v1/user-list', [
    'controller' => 'UserAccountController',
    'action' => 'userList'
]);

APIRoute::api('post', 'api/v1/user-create', [
    'controller' => 'UserAccountController',
    'action' => 'userCreated'
]);

APIRoute::api('post', 'api/v1/user-update', [
    'controller' => 'UserAccountController',
    'action' => 'userUpdate'
]);
APIRoute::api('post', 'api/v1/user-delete', [
    'controller' => 'UserAccountController',
    'action' => 'userDelete'
]);

/**
 * КОНТТРОЛЛЕР КОМПАНИЙ
 * Получение списка компаний
 */
APIRoute::api('post', 'api/v1/company/list', [
    'controller' => 'CompanyController',
    'action' => 'getCompanyList'
]);

/**
 * Метод создания новой компании с горячим добавлением счетов
 */
APIRoute::api('post', 'api/v1/company/create', [
    'controller' => 'CompanyController',
    'action' => 'createCompany'
]);

/**
 * Метод получения детальной информации о компании
 */
APIRoute::api('post', 'api/v1/company/get', [
    'controller' => 'CompanyController',
    'action' => 'detailsCompany'
]);

/**
 * Метод удаления компании и её счетов
 */
APIRoute::api('post', 'api/v1/company/deleted', [
    'controller' => 'CompanyController',
    'action' => 'deletedCompany'
]);



/**
 * Метод получение списка платежных аккаунтов
 */
APIRoute::api('post', 'api/v1/payment-account/list', [
    'controller' => 'PaymentAccountController',
    'action' => 'getPaymentAccountsList'
]);

/**
 * Метод удаления платежного аккаунта
 */
APIRoute::api('post', 'api/v1/payment-account/deleted', [
    'controller' => 'PaymentAccountController',
    'action' => 'deletedPaymentAccounts'
]);

/**
 * Метод создания новых платежных счетов компании
 */
APIRoute::api('post', 'api/v1/payment-account/create', [
    'controller' => 'PaymentAccountController',
    'action' => 'createPaymentAccounts'
]);


/**
 * Метод создания новых платежных счетов компании
 */
APIRoute::api('post', 'api/v1/payment-account/update', [
    'controller' => 'PaymentAccountController',
    'action' => 'updatePaymentAccount'
]);


/**
 * Получение платежных счетов компании
 */
APIRoute::api('post', 'api/v1/payment-account/selected-list', [
    'controller' => 'PaymentAccountController',
    'action' => 'getPaymentAccountsListShort'
]);

/**
 * Получение всех транзакций выбранного счета
 */
APIRoute::api('post', 'api/v1/payment-account/history', [
    'controller' => 'PaymentAccountController',
    'action' => 'getTransactionHistory'
]);

/**
 * Получение всех активных партнеров
 */
APIRoute::api('post', 'api/v1/partners/list', [
    'controller' => 'PartnerController',
    'action' => 'getPartnerList'
]);

/**
 * Добавление нового партенра
 */
APIRoute::api('post', 'api/v1/partners/add', [
    'controller' => 'PartnerController',
    'action' => 'addPartner'
]);

/**
 * Обновление данных партнера.
 */
APIRoute::api('post', 'api/v1/partners/update', [
    'controller' => 'PartnerController',
    'action' => 'update'
]);

/**
 * Удаление партенра.
 */
APIRoute::api('post', 'api/v1/partners/delete', [
    'controller' => 'PartnerController',
    'action' => 'deletePartner'
]);

/**
 * Получение списка складов
 */
APIRoute::api('post', 'api/v1/warehouse/list', [
    'controller' => 'WarehouseController',
    'action' => 'getWarehouseList'
]);


/**
 * Удаление склада из компании
 */
APIRoute::api('post', 'api/v1/warehouse/delete', [
    'controller' => 'WarehouseController',
    'action' => 'deletedWarehouse'
]);

/**
 * Редактирование склада
 */
APIRoute::api('post', 'api/v1/warehouse/update', [
    'controller' => 'WarehouseController',
    'action' => 'update'
]);

/**
 * Создание нового склада
 */
APIRoute::api('post', 'api/v1/warehouse/create', [
    'controller' => 'WarehouseController',
    'action' => 'createWarehouse'
]);


/**
 * Создание нового склада
 */

APIRoute::api('post', 'api/v1/warehouse/delete-stock-transaction', [
    'controller' => 'WarehouseController',
    'action' => 'deleteStockTransaction'
]);





/**
 * Получение всех активных партнеров для создания продукта
 */
APIRoute::api('post', 'api/v1/partner/get-partner', [
    'controller' => 'PartnerController',
    'action' => 'getPartnerListFotProduct'
]);


/**
 * Получение всех активных складов для создания продукта
 */
APIRoute::api('post', 'api/v1/warehouse/get-warehouse', [
    'controller' => 'WarehouseController',
    'action' => 'getWarehouseListFotProduct'
]);

/**
 * Сохранение продукта в справочнике
 */
APIRoute::api('post', 'api/v1/product/created', [
    'controller' => 'ProductController',
    'action' => 'createProduct'
]);

/**
 * Получение продуктов
 */
APIRoute::api('post', 'api/v1/product/list', [
    'controller' => 'ProductController',
    'action' => 'getProductsList'
]);

/**
 * Получение продуктов для выбора товара, короткий вариант
 */
APIRoute::api('post', 'api/v1/product/short-list', [
    'controller' => 'ProductController',
    'action' => 'getProductsListShort'
]);

/**
 * Получение продуктов для выбора товара, короткий вариант
 */
APIRoute::api('post', 'api/v1/warehouse/short-list', [
    'controller' => 'WarehouseController',
    'action' => 'getWarehouseListShort'
]);
/**
 * Получение продуктов для выбора товара, короткий вариант
 */
APIRoute::api('post', 'api/v1/warehouse/material-transactions', [
    'controller' => 'WarehouseController',
    'action' => 'materialTransactions'
]);

/**
 * Получение деталей продукта по ID
 */
APIRoute::api('post', 'api/v1/product/details', [
    'controller' => 'ProductController',
    'action' => 'getProductDetails'
]);

/**
 * Поставка продукции
 */
APIRoute::api('post', 'api/v1/product/new-supply', [
    'controller' => 'ProductController',
    'action' => 'addStockTransaction'
]);

/**
 * Поставка продукции
 */
APIRoute::api('post', 'api/v1/warehouse/stock', [
    'controller' => 'WarehouseController',
    'action' => 'getWarehouseStock'
]);

/**
 * Перемещение продукции
 */
APIRoute::api('post', 'api/v1/warehouse/transfer', [
    'controller' => 'WarehouseController',
    'action' => 'WarehouseTransfer'
]);




/**
 * Получение всех активных услуг
 */
APIRoute::api('post', 'api/v1/products/services-list', [
    'controller' => 'ProductController',
    'action' => 'getActiveServices'
]);

/**
 * /products/delete
 */
APIRoute::api('post', 'api/v1/products/delete', [
    'controller' => 'ProductController',
    'action' => 'deletedProduct'
]);










/**
 * ТРАНЗАКЦИИ
 */

/**
 * данные выбранного склада
 */
APIRoute::api('post', 'api/v1/warehouse/transactions', [
    'controller' => 'WarehouseController',
    'action' => 'getWarehouseTransactions'
]);

/**
 * данные выбранного партнера склада
 */
APIRoute::api('post', 'api/v1/partner/transactions', [
    'controller' => 'PartnerController',
    'action' => 'getPartnerTransactions'
]);



/**
 * ЗАКАЗЫ TASK ORDER
 */
APIRoute::api('post', 'api/v1/orders/create', [
    'controller' => 'OrderController',
    'action' => 'createOrder'
]);


APIRoute::api('post', 'api/v1/orders/updated', [
    'controller' => 'OrderController',
    'action' => 'updatedOrder'
]);


APIRoute::api('post', 'api/v1/orders/get-task-edit', [
    'controller' => 'OrderController',
    'action' => 'getOrderEdit'
]);





APIRoute::api('post', 'api/v1/orders/download-attachment', [
    'controller' => 'OrderController',
    'action' => 'downloadAttachment'
]);




APIRoute::api('post', 'api/v1/orders/update-status', [
    'controller' => 'OrderController',
    'action' => 'updateOrderAndItemsStatus'
]);


APIRoute::api('post', 'api/v1/orders/update-item-status', [
    'controller' => 'OrderController',
    'action' => 'updateItemsStatus'
]);


APIRoute::api('post', 'api/v1/services/add-service', [
    'controller' => 'OrderController',
    'action' => 'updatedServices'
]);





APIRoute::api('post', 'api/v1/transactions/delete', [
    'controller' => 'PaymentTransactionController',
    'action' => 'deleteTransaction'
]);



APIRoute::api('post', 'api/v1/balance/add', [
    'controller' => 'PaymentAccountController',
    'action' => 'addFundsToBalance'
]);



APIRoute::api('post', 'api/v1/balance/withdraw-funds', [
    'controller' => 'PaymentAccountController',
    'action' => 'withdrawFunds'
]);



APIRoute::api('post', 'api/v1/stock/recent-deliveries', [
    'controller' => 'WarehouseController',
    'action' => 'getRecentDeliveries'
]);



APIRoute::api('post', 'api/v1/order-item-materials/delete', [
    'controller' => 'OrderController',
    'action' => 'deletedMaterialsItem'
]);



APIRoute::api('post', 'api/v1/order-item-materials/add', [
    'controller' => 'OrderController',
    'action' => 'addMaterialsItem'
]);





APIRoute::api('post', 'api/v1/partner/partner-data', [
    'controller' => 'PartnerController',
    'action' => 'partnerOrderList'
]);






/**
 * Получение списка мастеров для заказа
 */
APIRoute::api('post', 'api/v1/masters/list', [
    'controller' => 'MastersController',
    'action' => 'MastersList'
]);

/**
 * Получение списка мастеров для заказа
 */
APIRoute::api('post', 'api/v1/masters/list', [
    'controller' => 'MastersController',
    'action' => 'MastersList'
]);

/**
 * Получение списка мастеров для заказа 2
 */
APIRoute::api('post', 'api/v1/masters/task-list', [
    'controller' => 'MastersController',
    'action' => 'MastersTaskList'
]);

/**
 * Получение списка мастеров для заказа
 */
APIRoute::api('post', 'api/v1/task/loading-contractors', [
    'controller' => 'MastersController',
    'action' => 'MastersContractorsList'
]);


/**
 * Транзакции конкретного мастера
 */
APIRoute::api('post', 'api/v1/masters/transactions', [
    'controller' => 'MastersController',
    'action' => 'MastersTransaction'
]);

/**
 * Получение списка мастеров (исполнителей)
 */
APIRoute::api('post', 'api/v1/contractor-create', [
    'controller' => 'MastersController',
    'action' => 'CreatedContractor'
]);





/**
 * Обновление данных заказа мастеров
 */
APIRoute::api('post', 'api/v1/masters/updated-task', [
    'controller' => 'MastersController',
    'action' => 'UpdatedContractorToTask'
]);


/**
 * Обновление данных заказа мастеров
 */
APIRoute::api('post', 'api/v1/masters/updated-task-c-t', [
    'controller' => 'MastersController',
    'action' => 'UpdatedContractorToTransaction'
]);





/**
 * Удаление мастера.
 */
APIRoute::api('post', 'api/v1/masters/delete-contractor-transaction', [
    'controller' => 'MastersController',
    'action' => 'DeleteContractorTransaction'
]);



/**
 * Удаление мастера.
 */
APIRoute::api('post', 'api/v1/order-item-service/delete', [
    'controller' => 'OrderController',
    'action' => 'deletedServiceItem'
]);


APIRoute::api('post', 'api/v1/product/translate', [
    'controller' => 'ProductController',
    'action' => 'translateProduct'
]);

APIRoute::api('post', 'api/v1/warehouse/product-transactions', [
    'controller' => 'ProductController',
    'action' => 'getProductTransactions'
]);
