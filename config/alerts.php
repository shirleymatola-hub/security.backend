<?php

/*
| Alertas externos de ocorrências (além das notificações dentro do sistema).
|
|   Nova ocorrência        → Chefe(s) de Permanência do posto      (email + SMS)
|   Prioridade alta/urgente → Comandante do Posto                  (email + SMS)
|   Ocorrência atribuída   → Agente atribuído                      (email + SMS)
|   Mudança de estado      → Chefe(s) de Permanência e agente      (email)
|
| O email usa a configuração MAIL_* e o SMS a configuração services.sms (SMS_DRIVER).
| Uma falha no envio fica registada no log e nunca impede a operação sobre a ocorrência.
*/

return [
    'mail' => (bool) env('ALERTS_MAIL', true),
    'sms' => (bool) env('ALERTS_SMS', true),
];
