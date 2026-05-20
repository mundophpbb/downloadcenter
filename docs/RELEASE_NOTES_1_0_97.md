# Release notes 1.0.97

Esta versão melhora a previsibilidade da entrega de arquivos e imagens pelo controller público.

## Alterações

- Downloads locais agora são enviados com cache privado/desativado, evitando reutilização indevida por navegador ou proxy.
- O nome sugerido do arquivo passa a usar título do item + versão, preservando a extensão original.
- Foi adicionado fallback ASCII para navegadores/servidores que não lidam bem com acentos no cabeçalho `Content-Disposition`.
- Respostas binárias recebem `X-Content-Type-Options: nosniff`.
- Downloads locais recebem `Accept-Ranges` e `Content-Length` quando possível.
- Capturas e imagens de itens passam a usar cabeçalhos privados de cache e metadados automáticos quando o Symfony disponível suportar.
- Redirecionamentos externos recebem cabeçalhos anti-cache antes do envio.

## Observações de atualização

Após atualizar, execute as migrations da extensão e limpe o cache do phpBB. Não há alteração estrutural de banco nesta versão além do marcador de versão.
