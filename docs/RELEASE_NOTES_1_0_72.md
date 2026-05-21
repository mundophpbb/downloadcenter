# Release notes - 1.0.72

## Correção

- Adicionada sanitização de texto antes de salvar dados da Central de Downloads.
- Remove caracteres Unicode de 4 bytes, principalmente emojis, para evitar erro SQL 1366 em hospedagens MySQL/MariaDB que usam `utf8` em vez de `utf8mb4`.
- Aplicado em descrição, resumo, changelog, legenda de capturas, categorias e outros campos textuais editáveis.

## Observação

Em bancos sem suporte a `utf8mb4`, emojis serão removidos automaticamente dos textos salvos pela extensão. BBCode e caracteres acentuados comuns continuam funcionando normalmente.
