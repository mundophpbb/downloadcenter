# Central de Downloads para phpBB

Extensão `mundophpbb/downloadcenter` para criar uma central de downloads, ferramentas e recursos dentro do phpBB.

## Estado

Versão: 1.0.49  
Estado: beta técnico / em validação

## Requisitos

- phpBB 3.3.x
- PHP 8.1 ou superior
- Permissão de escrita em `files/mundophpbb/downloadcenter/`
- ACP com acesso administrativo padrão (`a_board`)

## Recursos principais

- Categorias de downloads
- Itens com descrição, BBCode e changelog
- Histórico de versões
- Upload local e link externo
- Biblioteca de arquivos locais no ACP
- Download seguro via rota intermediária
- Contador de downloads com proteção contra contagem duplicada
- Envio público por usuários
- Aprovação/reprovação no ACP
- Meus envios para autores
- Edição limitada pelo autor
- Tópico de suporte automático
- Logs administrativos
- Painel estatístico
- Diagnóstico beta
- Regras públicas de submissão

## Instalação

1. Envie a pasta `ext/mundophpbb/downloadcenter/` para seu phpBB.
2. No ACP, vá em **Personalizar > Gerenciar extensões**.
3. Ative **Central de Downloads**.
4. Limpe o cache do phpBB.
5. Acesse **ACP > Extensões > Central de Downloads > Configurações**.
6. Revise extensões permitidas, tamanho máximo de upload, acesso público e envio público.
7. Acesse **Diagnóstico beta** para verificar pasta de arquivos, `.htaccess` e limites de upload.

## Pasta de arquivos

Arquivos enviados são armazenados em:

```text
files/mundophpbb/downloadcenter/
```

O acesso direto deve ser bloqueado pelo `.htaccess`; os downloads devem ocorrer pela rota da extensão.

## Observações de beta

Antes de usar em produção, teste upload, download, aprovação, exclusão de versões, biblioteca de arquivos e submissão pública em ambiente controlado.
