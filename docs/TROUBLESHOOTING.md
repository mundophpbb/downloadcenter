# Solução de problemas

## O botão de download não aparece

Verifique no ACP:

1. O item está ativo?
2. O item está aprovado?
3. O item possui versão atual?
4. A versão atual tem arquivo local ou URL externa válida?
5. O usuário tem permissão para baixar?
6. A Central está habilitada nas configurações?

Use a coluna **Status do download** em **Itens** para localizar a causa provável.

## Item aparece, mas download está indisponível

Possíveis causas:

- arquivo local apagado manualmente;
- versão local sem arquivo definido;
- URL externa ausente;
- URL externa inválida;
- permissão de download mais restrita que a visualização.

Abra **Integridade dos dados** e **Arquivos** para confirmar.

## Erro: opção de configuração não existe

Isso normalmente indica migration pendente ou chave de configuração antiga.

Ações recomendadas:

1. Confirme se os arquivos da versão nova foram enviados corretamente.
2. Acesse **Gerenciar extensões** para disparar migrations.
3. Limpe o cache do phpBB.
4. Abra **Diagnóstico beta** e confira a versão instalada.

## Upload local falha

Verifique:

- permissão de escrita em `files/mundophpbb/downloadcenter/`;
- limite `upload_max_filesize` do PHP;
- limite `post_max_size` do PHP;
- tamanho máximo configurado no ACP da extensão;
- extensão do arquivo permitida;
- `.htaccess` dentro da pasta de arquivos.

## Usuário não consegue enviar item

Verifique:

- se envios públicos estão habilitados;
- se o usuário está logado;
- se o usuário tem posts mínimos exigidos;
- se o modo ACL está ativo e o grupo tem `u_downloadcenter_submit`;
- se a visualização está mais restrita que o envio.

Visitantes nunca podem enviar itens.

## Administrador não vê pendências

Verifique:

- se há itens realmente pendentes;
- se o usuário tem permissão administrativa/moderadora suficiente;
- no modo ACL, se possui `m_downloadcenter_approve` ou `a_downloadcenter_manage`;
- se notificações administrativas estão habilitadas.

## Contadores parecem errados

Use:

```text
ACP > Extensões > Central de Downloads > Diagnóstico beta > Reconstruir dados derivados
```

Depois confira **Integridade dos dados**.

## Arquivos órfãos aparecem

Arquivos órfãos são arquivos físicos sem vínculo com versões cadastradas.

Eles podem surgir quando:

- uma versão foi excluída;
- um upload falhou parcialmente;
- arquivos foram copiados manualmente;
- versões foram editadas de local para externa.

Use a tela **Arquivos** para revisar e excluir órfãos com segurança.
