# Checklist de atualização

Use este checklist sempre que substituir os arquivos da extensão por uma nova versão.

## Antes de atualizar

- Faça backup do banco de dados.
- Faça backup da pasta `files/mundophpbb/downloadcenter/`.
- Anote a versão atual da extensão no ACP.
- Se possível, teste primeiro em ambiente de homologação.

## Atualização dos arquivos

1. Substitua a pasta `ext/mundophpbb/downloadcenter/` pela nova versão.
2. Preserve a pasta `files/mundophpbb/downloadcenter/`; ela não deve ser apagada.
3. Limpe o cache do phpBB.
4. Acesse **ACP > Personalizar > Gerenciar extensões** para aplicar migrations pendentes.
5. Verifique se a versão registrada em **Diagnóstico beta** corresponde à versão do pacote.

## Depois de atualizar

1. Abra **Diagnóstico beta**.
2. Verifique avisos sobre versão instalada, configurações e pasta de arquivos.
3. Execute **Manutenção segura > Reconstruir dados derivados**.
4. Abra **Integridade dos dados**.
5. Corrija alertas automáticos seguros, se existirem.
6. Abra **Arquivos** e confira se há órfãos inesperados.
7. Teste um download local.
8. Teste um download externo.
9. Teste a página pública da listagem.
10. Teste a página pública de detalhes.

## Se usar ACL do phpBB

- Confirme se o modo ACL ainda está ativo em **Configurações**.
- Revise grupos com as permissões:
  - `u_downloadcenter_view`
  - `u_downloadcenter_download`
  - `u_downloadcenter_submit`
  - `m_downloadcenter_approve`
  - `a_downloadcenter_manage`
- Limpe o cache depois de mudanças grandes em permissões.

## Sinais de problema após atualização

- Botão de download sumiu: verifique se o item tem versão atual e origem válida.
- Página pública vazia: verifique permissões, filtros ativos e categorias.
- Erro de configuração ausente: confirme se as migrations foram executadas.
- Arquivo local indisponível: verifique a pasta `files/mundophpbb/downloadcenter/` e a tela **Arquivos**.
- URL externa não redireciona: confirme se começa com `http://` ou `https://`.
