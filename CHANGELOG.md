## 1.0.73

- Added controlled BBCode support for short descriptions.
- Updated public catalog, item page, user resource list, and ACP review cards to render short description BBCode.
- Added help text recommending lightweight formatting in short descriptions.


## 1.0.72

- Corrigido erro SQL 1366 causado por emojis/caracteres Unicode de 4 bytes em bancos sem `utf8mb4`.
- Adicionada sanitização de texto em campos editáveis como descrição, changelog, categorias e legendas.


## 1.0.69

- Revisão de terminologia pública e administrativa, aproximando os textos do vocabulário de recursos/itens do phpBB.
- Substituição de ocorrências visíveis de “ferramenta” por “recurso” ou “item” quando mais adequado.
- Nova opção no ACP para exigir ou dispensar a confirmação das regras de publicação.
- Novo campo no ACP para informar um tópico/link externo com as regras da Central.
- A página interna de regras continua disponível quando nenhum link personalizado é configurado.

## 1.0.67 - Author screenshot delete fix

- Corrigido erro fatal ao excluir capturas pela edição do autor.
- Adicionado método seguro para remover arquivo físico de captura no controller público.

# Changelog

## 1.0.71

- Corrige erro fatal causado pela ausência do método `requires_rules_acceptance()` no controller público.
- Mantém o aceite das regras controlado pela configuração do ACP.

## 1.0.70

- Corrige erro fatal causado pela ausência do método `rules_url()` no controller público.
- Mantém suporte a URL externa/tópico fixo para regras de publicação, com fallback para a página interna.

## 1.0.66

- Added author-side screenshot management in the public edit screen.
- Added thumbnail list, caption/order editing and deletion for author screenshots.
- Screenshot changes made by authors now keep the review workflow consistent.


## 1.0.65

- Added optional screenshot upload to the public submission form.
- Added optional screenshot upload to the author edit form.
- Existing author screenshots are now visible in the author edit page.
- Screenshots uploaded by authors remain tied to the item and are reviewed together with the submission.

## 1.0.64

- Consolidated migration files for the beta package.
- Kept the current schema, configuration values and ACP modules in a cleaner migration tree.
- Added a compatibility update migration for installations already running the previous beta line.
- No functional changes.


## 1.0.61

- Polimento visual da página individual do item.
- Botão de download mais destacado e proporcional.
- Bloco de ações mais limpo com versão e downloads.
- Abas, capturas, changelog e versões anteriores refinados.
- Ajustes responsivos para telas menores.

## 1.0.59

- Added ACP data integrity diagnostics.
- Added checks for invalid item/version/file/screenshot/topic relationships.
- Added a safe review-only workflow: diagnostics do not delete data automatically.

# 1.0.57

- Polished screenshot gallery and added a lightweight lightbox.
- Improved frontend `[list]` rendering for changelogs.
- Added latest changelog review in the ACP item editor.

## 1.0.56

- Corrigida validação de nomes de arquivos.

## 1.0.55

- Corrigido erro de tabela indefinida na área de capturas no ACP.
- Área de capturas exibida também no cadastro inicial, com aviso para salvar o item antes do upload.


## 1.0.51 - Support forum selector

- Substitui o campo manual de ID do fórum por um seletor de fóruns postáveis no ACP.
- Mantém a opção de não criar tópicos automaticamente.
- Valida o fórum configurado antes de criar tópico de suporte.
- Melhora mensagens administrativas relacionadas ao fórum de suporte.

# Changelog

## 1.0.70

- Corrige erro fatal causado pela ausência do método `rules_url()` no controller público.
- Mantém suporte a URL externa/tópico fixo para regras de publicação, com fallback para a página interna.

## 1.0.63

- Added managed item image picker in the ACP.
- Added item image preview, upload, reuse and clear actions.
- Added option to use a screenshot as the main item image.
- Added controlled route for serving item images.

## 1.0.62

- Improved ACP screenshot workflow so screenshots can be added one at a time without losing the edit context.
- Added dedicated **Add screenshot** button and return-to-section behavior.
- Added inline success messages for screenshot upload/deletion.
- Clarified that screenshots are saved immediately while item changes still use the main save action.


## 1.0.58

- Corrigida renderização de listas ordenadas no changelog público.
- Adicionada edição do changelog da versão atual no ACP.


## 1.0.54

- Added item screenshots gallery in ACP and public item page.
- Added controlled screenshot serving route.


## 1.0.52

- Added support topic synchronization from ACP item saves.
- Support topics are created automatically when missing and a support forum is configured.
- Existing support topics have their first post updated with item description and latest version data.
- Kept version history focused on technical version metadata and changelog.


## 1.0.50 - Beta consolidation

- Atualizacao de versao para beta tecnica consolidada.
- Adicionado `docs/PRE_RELEASE_AUDIT.md`.
- Adicionado `docs/RELEASE_NOTES_1_0_50.md`.
- Sem adicao de recurso pesado; foco em preparacao para testes.

## 1.0.49

- Adicionado README do pacote beta.
- Adicionado changelog do projeto.
- Adicionado checklist de testes beta.
- Adicionada identificação de versão/estado no ACP.
- Preparação para distribuição beta controlada.

## 1.0.48

- Adicionada página pública de regras da Central.
- Adicionado aceite obrigatório das regras no envio público.
- Exibição automática das regras atuais de upload.

## 1.0.47

- Adicionada aba Diagnóstico beta no ACP.
- Checagem de pasta de arquivos, `.htaccess`, limites PHP, arquivos ausentes e arquivos órfãos.

## 1.0.46

- Adicionadas configurações de extensões permitidas e tamanho máximo de upload.
- Reforço na validação de arquivos enviados.

## 1.0.45

- Adicionada biblioteca de arquivos no ACP.
- Permite visualizar arquivos em uso, disponíveis e remover arquivos órfãos.

## 1.0.44

- Adicionado seletor de arquivos locais existentes no ACP.

## 1.0.43

- Corrigida renderização visual de listas BBCode.

## 1.0.40-1.0.42

- Melhorias de BBCode, tamanho automático de arquivo e polimento visual em Meus envios.

## 1.0.34-1.0.39

- Paginação, correções e ajuste visual inspirado em catálogo técnico.

## 1.0.1-1.0.33

- Base da extensão: ACP, frontend, categorias, itens, versões, upload, downloads, aprovação, logs, painel e notificações internas.

## 1.0.53
- Improved ACP version history readability.

## 1.0.60

- Improved ACP pending item review panel with card-based review summaries.
- Added version, download, changelog, screenshots and support topic information to the review view.
- Added quick review actions for pending items.
- Added pending panel pagination variables.
## 1.0.68

- Corrigida exclusão de capturas pelo autor no frontend.
- Reduzido o tamanho visual das miniaturas no gerenciador de capturas do autor.
