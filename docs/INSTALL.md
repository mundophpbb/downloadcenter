# Instalação e atualização

## Instalação nova

1. Copie `ext/mundophpbb/downloadcenter/` para a instalação do phpBB.
2. Ative a extensão no ACP.
3. Limpe o cache.
4. Abra as configurações da extensão.
5. Execute o Diagnóstico beta.

## Atualização de uma versão anterior

1. Desative a extensão apenas se necessário. Na maioria dos testes, basta substituir arquivos.
2. Substitua a pasta `ext/mundophpbb/downloadcenter/` pela nova versão.
3. Limpe o cache do phpBB.
4. Acesse **Personalizar > Gerenciar extensões** para aplicar migrations pendentes.
5. Abra o Diagnóstico beta e revise avisos.

## Pós-instalação recomendada

- Confirmar se `files/mundophpbb/downloadcenter/` é gravável.
- Confirmar se `.htaccess` existe dentro da pasta de arquivos.
- Configurar extensões permitidas.
- Configurar tamanho máximo de upload.
- Criar pelo menos uma categoria.
- Criar um item de teste com link externo.
- Criar um item de teste com arquivo local.
