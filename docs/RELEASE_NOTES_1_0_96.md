# Release notes 1.0.96

## Segurança de upload

- Extensões perigosas agora são bloqueadas mesmo se forem inseridas manualmente na configuração de extensões permitidas.
- Nomes com dupla extensão perigosa, como `arquivo.php.zip`, continuam recusados.
- Uploads vazios agora são recusados antes da gravação.
- A normalização das extensões permitidas remove automaticamente tipos executáveis ou interpretáveis.

## Compatibilidade

- Não altera registros existentes.
- Mantém as extensões de download já configuradas, exceto extensões bloqueadas por segurança.
