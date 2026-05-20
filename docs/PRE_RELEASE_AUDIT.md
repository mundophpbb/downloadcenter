# Auditoria pre-release beta - Download Center 1.0.50

Este documento resume os pontos verificados antes de distribuir a extensao como beta tecnica.

## Estrutura do pacote

- Caminho da extensao: `ext/mundophpbb/downloadcenter/`
- Vendor/ext: `mundophpbb/downloadcenter`
- Namespace principal: `mundophpbb\downloadcenter`
- Versao declarada no `composer.json`: `1.0.50`
- Versao interna atualizada por migration: `mundophpbb_downloadcenter_version = 1.0.50`

## Fluxos principais para teste manual

1. Ativar/desativar a extensao no ACP.
2. Criar, editar e excluir categorias.
3. Criar item com link externo.
4. Criar item com upload local.
5. Adicionar nova versao a um item existente.
6. Selecionar arquivo local existente pela biblioteca.
7. Enviar ferramenta pelo frontend com usuario comum.
8. Aprovar/reprovar item em Itens pendentes.
9. Baixar arquivo local e conferir contador.
10. Baixar link externo e conferir contador.
11. Editar item pelo autor em Meus envios.
12. Confirmar que item editado volta para aprovacao quando necessario.
13. Excluir versao e conferir arquivo fisico associado.
14. Excluir item e conferir limpeza de versoes/arquivos/logs relacionados.
15. Conferir notificacoes internas do phpBB.
16. Conferir Diagnostico beta.
17. Conferir Arquivos no ACP.
18. Conferir paginacao com volume maior de itens/logs.

## Pontos de atencao antes de liberar publicamente

- Testar em ambiente limpo, instalando a extensao do zero.
- Testar atualizacao sobre uma instalacao existente da versao anterior.
- Testar com `URL rewriting` ligado e desligado.
- Testar upload em hospedagem real, pois limites de PHP variam.
- Conferir permissao de escrita da pasta `files/mundophpbb/downloadcenter/`.
- Conferir se o `.htaccess` esta presente na pasta de arquivos.
- Revisar extensoes permitidas no ACP antes de aceitar envios publicos.
- Revisar tamanho maximo de upload no ACP e comparar com `upload_max_filesize` e `post_max_size`.

## Estado recomendado

Esta versao deve ser tratada como **beta tecnica**. A extensao esta funcional, mas ainda deve passar por testes com dados reais e usuarios diferentes antes de ser considerada estavel.
