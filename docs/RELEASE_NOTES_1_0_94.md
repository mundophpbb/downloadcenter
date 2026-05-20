# Release notes - 1.0.94

Esta versão faz um acabamento de acessibilidade e resistência visual no frontend público.

## Alterações

- Labels ocultos nos filtros públicos para leitores de tela.
- Resumo de resultados com `aria-live`.
- Cards públicos com associação semântica ao título do item.
- Ações de card com `aria-label` mais descritivo.
- Abas da página de detalhes com `role=tab`, `aria-selected`, `aria-controls` e navegação por teclado.
- Painéis de abas ocultos com `hidden` quando inativos.
- Foco visível consistente em links, botões, campos e elementos interativos.
- Tratamento de `prefers-reduced-motion`.
- Grid público mais tolerante a temas estreitos do phpBB.

## Pós-atualização

- Execute as migrations da extensão.
- Limpe o cache do phpBB.
- Recarregue a página pública com cache do navegador limpo se o CSS antigo persistir.
