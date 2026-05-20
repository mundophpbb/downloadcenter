# Migrations consolidadas - MundophpBB Download Center

Este pacote consolida a cadeia de migrations da extensão.

## Arquivos mantidos

- `v_1_0_0.php`: migration base, já com o schema e as configurações finais para instalações novas.
- `v_1_0_99.php`: migration final de compatibilidade para instalações existentes.

## Arquivos removidos

As migrations incrementais `v_1_0_64.php` até `v_1_0_98.php` foram removidas porque, na prática, a maior parte delas apenas atualizava a chave de versão da extensão. As alterações estruturais relevantes foram incorporadas à migration base e à migration final de compatibilidade.

## Compatibilidade

A migration `v_1_0_99.php` não depende mais da cadeia incremental antiga. Ela depende apenas de `v_1_0_0` e verifica/adiciona com segurança:

- coluna `item_current_version_id` em `downloadcenter_items`, quando ausente;
- índice `current_version_id`, quando ausente;
- configurações finais ausentes;
- permissões ACL do phpBB usadas pela extensão;
- módulos ACP na ordem final.

## Pós-atualização recomendado

Depois de substituir os arquivos:

1. Limpe o cache do phpBB.
2. Acesse o ACP para executar as migrations pendentes.
3. Acesse a tela **Diagnóstico beta** da extensão.
4. Rode **Manutenção segura** se necessário.
