# Guia administrativo da Central de Downloads

Este guia resume o fluxo recomendado para administrar a extensão `mundophpbb/downloadcenter` no ACP.

## Fluxo recomendado

1. Configure as opções globais em **Configurações**.
2. Crie categorias antes de cadastrar itens.
3. Cadastre o item em **Itens > Adicionar item**.
4. Salve primeiro os **Dados do item**.
5. Depois adicione a primeira versão em **Dados da versão**.
6. Confira o **Status do download** na listagem de itens.
7. Use **Integridade dos dados** quando houver arquivo ausente, versão sem origem ou contadores divergentes.
8. Use **Diagnóstico beta > Manutenção segura** depois de atualizações maiores.

## Diferença entre item e versão

### Item

Representa a página pública do download. Contém título, descrição, categoria, autor, imagem, status e tópico de suporte.

### Versão

Representa uma publicação baixável do item. Contém número da versão, changelog, compatibilidade, tipo de origem e arquivo ou URL externa.

Um item pode existir sem versão, mas nesse caso o botão de download não será exibido publicamente.

## Status operacional

A coluna **Status do download** ajuda a identificar rapidamente problemas comuns:

- **Pronto para download**: item publicado, com versão atual válida e origem disponível.
- **Sem versão**: item cadastrado, mas sem versão publicada.
- **Arquivo ausente**: versão local aponta para arquivo inexistente.
- **Arquivo local não definido**: versão local não tem nome de arquivo associado.
- **URL externa ausente**: versão externa não tem URL.
- **URL externa inválida**: versão externa usa URL inválida ou sem `http://` / `https://`.
- **Aguardando aprovação**: item ainda precisa ser aprovado.
- **Item inativo**: item existe, mas não está visível ao público.
- **Restrito a administradores**: configuração/permissão impede acesso comum.

## Versão atual

Cada item pode ter uma versão marcada como atual. A página pública usa essa versão para o botão principal de download.

No histórico de versões do ACP é possível:

- adicionar versão;
- editar versão;
- definir como atual;
- excluir versão com fallback automático;
- manter versões antigas disponíveis quando permitido.

## Arquivos locais

Arquivos locais ficam em:

```text
files/mundophpbb/downloadcenter/
```

A extensão deve servir os arquivos pela rota de download, não por acesso direto ao diretório.

Use a tela **Arquivos** para identificar:

- arquivos em uso;
- arquivos órfãos;
- quantidade de vínculos;
- tamanho e extensão dos arquivos.

## Permissões

A extensão possui dois modos de permissões:

### Regras globais da extensão

Modo padrão. Usa as configurações próprias do ACP da extensão.

Regras aplicadas:

- baixar nunca pode ser mais liberado que visualizar;
- enviar nunca pode ser mais liberado que visualizar;
- visitantes nunca podem enviar.

### Permissões ACL do phpBB

Modo avançado. Usa permissões nativas do phpBB:

- `u_downloadcenter_view`
- `u_downloadcenter_download`
- `u_downloadcenter_submit`
- `m_downloadcenter_approve`
- `a_downloadcenter_manage`

Depois de ativar esse modo, revise as permissões em **Permissões > Permissões de grupos/usuários**.

## Envios públicos

Quando envios públicos estão habilitados:

- usuários podem enviar itens conforme permissões;
- novos envios ficam pendentes quando moderação estiver ativa;
- administradores veem avisos de pendência;
- autores podem acompanhar o estado em **Meus envios**.

## Telas de manutenção

### Painel

Mostra resumo operacional, itens com problema e atalhos rápidos.

### Integridade dos dados

Aponta inconsistências e oferece correções automáticas seguras quando possível.

### Arquivos

Ajuda a localizar arquivos locais em uso ou órfãos.

### Diagnóstico beta

Verifica instalação, configurações essenciais, permissões, pasta de upload e oferece a ação **Reconstruir dados derivados**.

### Logs

Registra ações administrativas, correções automáticas, exclusões, mudanças em versões e eventos de integridade.
