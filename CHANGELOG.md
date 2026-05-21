## 1.0.99

- Revisadas traduções e termos para ficarem mais próximos do padrão phpBB.
- Adicionada verificação pré-lançamento no Diagnóstico beta.
- Adicionada exportação de relatório de diagnóstico em TXT.
- Adicionadas estatísticas públicas opcionais no índice da Central.
- Adicionado feed RSS opcional para downloads publicados recentemente.
- Adicionado rate limit básico de downloads por usuário/IP.
- Documentada a política de consolidação/auditoria das migrations.

## 1.0.98

- Otimizada a listagem pública para buscar as versões atuais dos itens da página em lote, reduzindo consultas repetidas.
- Limitado o valor efetivo de itens por página a 50 para evitar páginas públicas pesadas por configuração acidental.
- Adicionado indicador de intervalo exibido na listagem pública, mostrando quais registros da página atual estão sendo apresentados.
- Ajustada a responsividade do resumo de resultados da listagem pública.

## 1.0.97

- Melhorada a entrega de arquivos locais com cabeçalhos privados e `no-store` para downloads.
- Adicionado fallback ASCII para nomes de arquivo com acentos/caracteres especiais no `Content-Disposition`.
- Adicionados cabeçalhos `X-Content-Type-Options: nosniff`, `Accept-Ranges` e `Content-Length` quando aplicável.
- Imagens/capturas servidas pela extensão agora recebem cabeçalhos de cache privado e metadados automáticos quando suportado pelo Symfony.
- Redirecionamentos para downloads externos agora recebem cabeçalhos anti-cache antes do redirecionamento.
- Adicionadas notas de release da versão 1.0.97.

## 1.0.96

- Reforçada a segurança dos uploads locais no ACP e no frontend.
- Extensões executáveis/interpretáveis agora são bloqueadas mesmo se adicionadas manualmente à lista de extensões permitidas.
- Uploads vazios passam a ser recusados antes da gravação.
- Adicionadas notas de release da versão 1.0.96.

# Changelog

## 1.0.95

- Refinamento visual e responsivo do ACP.
- Melhor consistência para blocos de aviso, guia rápido, release beta e alertas pendentes.
- Melhoria de foco visível e navegação por teclado nas áreas administrativas.
- Ajustes de responsividade em cartões, ações, filtros de logs e biblioteca de arquivos.
- Adicionado `docs/RELEASE_NOTES_1_0_95.md`.

## 1.0.94

- Melhorada a acessibilidade da listagem pública com labels ocultos, foco visível e resumo de resultados anunciado por leitores de tela.
- Aprimoradas as abas da página de detalhes com ARIA, navegação por teclado e painéis ocultos corretamente.
- Ajustada a responsividade dos cards públicos para maior resistência a temas phpBB estreitos.
- Adicionados ajustes de redução de movimento e estados desativados mais consistentes.


## 1.0.93

- Adicionada documentação administrativa consolidada.
- Adicionado checklist de atualização pós-update.
- Adicionado guia de solução de problemas comuns.
- README e documentação de instalação atualizados para refletir recursos recentes.

## 1.0.92

- Adicionada manutenção segura no Diagnóstico beta para reconstruir dados derivados após atualização/importação.
- A manutenção revisa versão atual, recalcula contadores, limpa origem de download conflitante e recria proteção básica da pasta local quando possível.
- Adicionados logs e traduções para a ação de manutenção.

## 1.0.91

- Diagnóstico beta ampliado com verificação de versão instalada, chaves essenciais de configuração, modo de permissões, fórum de suporte, envios públicos e paginação pública.
- Resumo do diagnóstico agora exibe versão instalada e modo de permissões ativo.
- Traduções PT-BR/EN revisadas para as novas mensagens de diagnóstico.

## 1.0.90
- Added an ACL diagnostics checklist to the ACP settings screen.
- The settings page now lists each phpBB ACL permission used by the extension and whether the current administrator has it.
- Added clearer guidance for administrators when switching from global rules to phpBB ACL mode.

## 1.0.88

## 1.0.89

- Adicionado modo opcional de permissões ACL do phpBB.
- Criadas permissões: `u_downloadcenter_view`, `u_downloadcenter_download`, `u_downloadcenter_submit`, `m_downloadcenter_approve` e `a_downloadcenter_manage`.
- Mantido o modo de regras globais como padrão para não quebrar instalações existentes.
- Atualizada a tela de Configurações com seletor de modo de permissões e explicações.
- Ajustadas verificações públicas de visualizar, baixar e enviar para respeitar ACL quando o modo estiver ativo.


- Melhorado o aviso administrativo de envios pendentes na página pública.
- Adicionado resumo dos envios pendentes mais recentes para administradores.
- Reduzida duplicação de notificações internas não lidas para o mesmo item pendente.

# Changelog

## 1.0.95

- Refinamento visual e responsivo do ACP.
- Melhor consistência para blocos de aviso, guia rápido, release beta e alertas pendentes.
- Melhoria de foco visível e navegação por teclado nas áreas administrativas.
- Ajustes de responsividade em cartões, ações, filtros de logs e biblioteca de arquivos.
- Adicionado `docs/RELEASE_NOTES_1_0_95.md`.

## 1.0.87

- Melhorada a página “Meus envios” com painel de totais por status.
- Adicionados filtros por texto e status: todos, publicados, pendentes, desativados e sem versão.
- Adicionadas mensagens contextuais explicando o estado de cada envio do usuário.
- Reorganizadas ações de edição e visualização pública dos próprios envios.

## 1.0.86

- Melhorada a tela pública de envio com fluxo em três etapas: dados do item, primeira versão e revisão.
- Adicionado destaque visual de envio moderado e checklist antes de enviar para aprovação.
- Reorganizada a escolha da origem do download entre link externo e arquivo local.
- Revisadas traduções em português e inglês para os novos textos do formulário público.

## 1.0.82

## 1.0.83

- Melhorada a página pública de detalhes com cabeçalho mais claro, status visual, resumo operacional e ações de download mais evidentes.
- Reorganizadas as abas de descrição, changelog, versões e capturas para leitura mais profissional.
- Revisadas traduções públicas de compatibilidade phpBB/PHP usadas nos cards e na página de detalhes.

- Melhorada a listagem pública com cards responsivos em duas colunas quando houver espaço.
- Adicionadas etiquetas visuais de status: disponível, sem versão, indisponível, restrito e atualizado recentemente.
- Reorganizadas informações de versão, compatibilidade, tamanho, atualização, autor e ações nos cards públicos.
- Mantida responsividade para reduzir automaticamente para uma coluna em telas pequenas.

## 1.0.81

- Melhora a apresentação da tela de Logs no ACP com cartões, filtros mais organizados e resumo de ações em chips clicáveis.
- Corrige chaves de idioma ausentes que apareciam como `ACP_DOWNLOADCENTER_*` no painel.
- Padroniza rótulos de referências de item/versão e melhora a legibilidade da tabela de logs.

## 1.0.77

## 1.0.80

- Melhora a tela de Logs com cartões de resumo, contagem de registros do dia e total de eventos de integridade.
- Adiciona filtros por versão, trecho da mensagem e intervalo de datas.
- Exibe rótulos legíveis para ações recentes, incluindo integridade, biblioteca de arquivos, imagens e changelog.
- Adiciona atalhos para editar o item relacionado e filtrar logs por item ou versão.

## 1.0.79

- Adiciona correções automáticas seguras na tela de Integridade dos dados.
- Permite corrigir versão atual inválida, limpar origem mista de download e recalcular contadores.
- Permite remover registros órfãos de downloads, capturas e versões quando o vínculo pai já não existe.


## 1.0.78

- Aprimora a tela de Integridade dos dados com checagens adicionais para versão atual inválida, versões órfãs, URLs externas inválidas, origem de download misturada e contadores divergentes.
- Adiciona ações rápidas na área de integridade para abrir Itens, Arquivos, Configurações e atualizar o diagnóstico.


- Melhorada a biblioteca de arquivos locais no ACP com filtro por uso, extensão visível e contagem de vínculos.
- Adicionado aviso para arquivos órfãos e ação controlada para excluir somente arquivos não vinculados a versões.
- Reforçada a proteção para preservar arquivos usados por uma ou mais versões durante a limpeza da biblioteca.

## 1.0.76

- Melhorada a exclusão de versões no ACP.
- Ao excluir a versão atual, a extensão agora define automaticamente como atual a versão publicada mais recente restante.
- A exclusão de arquivo físico evita remover arquivos locais ainda usados por outra versão.
- Atualizada a mensagem de confirmação para explicar o efeito da exclusão da versão atual.

## 1.0.75
- Added ACP editing for existing version records from version history.
- Version form now switches between add and edit modes, with cancel action.
- Existing version metadata, source and changelog can be updated without creating a new version.

## 1.0.74

- Melhorada a validação e exibição da origem de download das versões no ACP.
- Versões externas agora limpam dados de arquivo local ao salvar; versões locais limpam a URL externa.
- O histórico de versões agora mostra status específico para link externo válido/inválido, URL ausente, arquivo local disponível, ausente ou não definido.
- Adicionado atalho para abrir links externos válidos diretamente no histórico.
- A rota direta de download agora bloqueia URLs externas inválidas antes do redirecionamento.

## 1.0.73

- Melhorado o Painel do ACP com cartões de saúde operacional.
- Adicionado resumo de itens prontos, sem versão, com problemas de arquivo/link e restritos por permissões.
- Adicionada lista rápida de itens que precisam de atenção com link direto para edição.

## 1.0.72

- Melhorado o bloco de permissões globais no ACP com resumo prático, cartões de acesso e alertas contextuais.
- Centralizada a normalização da permissão de envio para evitar configurações ambíguas.

## 1.0.71

- Adicionada seleção explícita da versão atual no histórico de versões do ACP.
- A página pública passa a priorizar a versão marcada como atual, com fallback automático para a versão mais recente.
- Ao adicionar uma nova versão, ela passa a ser marcada como atual automaticamente.
- Ao excluir a versão atual, a referência é limpa para permitir o fallback seguro.

## 1.0.70

- Adicionado status operacional do download na listagem de itens do ACP.
- Adicionados avisos contextuais na edição do item para casos como item sem versão, arquivo ausente, URL externa vazia, item inativo, pendente ou restrito por permissões.

## 1.0.67 - Author screenshot delete fix

- Corrigido erro fatal ao excluir capturas pela edição do autor.
- Adicionado método seguro para remover arquivo físico de captura no controller público.

## 1.0.85
- Improved public category navigation with responsive category overview cards.
- Added selected-category summary with item count, download count and latest update.
- Category links now preserve the current search, compatibility filters and sorting when switching categories.
- Added public translations for the new category overview labels.

## 1.0.84
- Melhorada a busca e filtragem pública da Central de Downloads.
- Adicionados filtros públicos por compatibilidade phpBB e PHP.
- Adicionada ordenação por atualização recente.
- Melhorada a mensagem de resultados e estado vazio quando filtros não retornam itens.

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

## 1.0.95

- Refinamento visual e responsivo do ACP.
- Melhor consistência para blocos de aviso, guia rápido, release beta e alertas pendentes.
- Melhoria de foco visível e navegação por teclado nas áreas administrativas.
- Ajustes de responsividade em cartões, ações, filtros de logs e biblioteca de arquivos.
- Adicionado `docs/RELEASE_NOTES_1_0_95.md`.

## 1.0.84
- Melhorada a busca e filtragem pública da Central de Downloads.
- Adicionados filtros públicos por compatibilidade phpBB e PHP.
- Adicionada ordenação por atualização recente.
- Melhorada a mensagem de resultados e estado vazio quando filtros não retornam itens.

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
## 1.0.69
- Reordenado o menu do ACP por prioridade de uso: Painel, Configurações, Categorias, Itens, Itens pendentes, Arquivos, Integridade, Diagnóstico beta e Logs.
- Adicionada migração para recriar os módulos do ACP nessa ordem em instalações já existentes.

## 1.0.68

- Corrigida exclusão de capturas pelo autor no frontend.
- Reduzido o tamanho visual das miniaturas no gerenciador de capturas do autor.
