# Release Notes 1.0.55

## Correções

- Corrige erro no ACP onde a tabela de capturas de tela não era inicializada no modo Itens.
- Evita SQL inválido `SELECT * FROM WHERE ...` ao editar um item.
- A área de capturas agora também aparece ao criar um novo item, com aviso explicando que o item precisa ser salvo antes de anexar imagens.

## Observação

As capturas de tela continuam vinculadas ao item principal. Por isso, o upload só fica disponível depois que o item possui `item_id`.
