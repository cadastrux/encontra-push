# Encontra Push — plugin WordPress

Camada de integração entre um site WordPress e o painel **Encontra Push**
(`push.encontra.com.br`). O plugin cuida do que só pode acontecer dentro da
origem do site — permissão do navegador, Service Worker, captura de
subscriptions e rastreio de clique. Campanhas, segmentação, fila de envio e
métricas ficam no painel.

A documentação de uso está em [`readme.txt`](readme.txt), no formato do
WordPress.

## O conteúdo deste repositório é o plugin

A raiz daqui é a pasta do plugin. O que está neste repositório é exatamente o
que vai para `wp-content/plugins/encontra-push/` — sem build, sem dependência,
sem etapa de empacotamento.

## Publicar uma versão

O plugin se atualiza sozinho lendo as **tags** deste repositório. Não é preciso
criar release nem anexar zip: o pacote é o zipball da tag.

O número da tag e o da versão no código **precisam ser iguais**, senão o
WordPress oferece a atualização e, depois de instalar, continua vendo a versão
antiga — e oferece de novo, em looping.

A versão aparece em quatro lugares:

| Arquivo | Onde |
|---|---|
| `encontra-push.php` | cabeçalho `Version:` |
| `encontra-push.php` | `define( 'ENCONTRA_PUSH_VERSION', ... )` |
| `readme.txt` | `Stable tag:` e uma entrada em `== Changelog ==` |
| `languages/encontra-push.pot` | `Project-Id-Version` |

Com os quatro atualizados:

```bash
git add -A
git commit -m "1.0.15 — descricao curta"
git tag v1.0.15
git push origin main --tags
```

Em até 6 horas (ou na hora, clicando em **Verificar novamente** no Painel →
Atualizações) os sites passam a mostrar a atualização.

## Como a atualização funciona

`includes/class-updater.php` responde ao filtro `update_plugins_github.com`,
ativado pelo cabeçalho `Update URI:` do arquivo principal. Ele lê
`/repos/cadastrux/encontra-push/tags`, escolhe a maior versão no formato
`v1.2.3` e devolve ao WordPress uma resposta no mesmo formato que o
wordpress.org devolveria.

Declarar o `Update URI` tem um efeito de segurança: o WordPress para de
consultar o wordpress.org para este plugin. Sem isso, bastaria alguém publicar
um plugin com o slug `encontra-push` no diretório oficial para que os sites
baixassem o pacote dele por cima deste.

A consulta fica em cache por 6 horas. O limite da API do GitHub sem
autenticação é de 60 requisições por hora por IP.

## Repositório privado

Com o repositório **público**, nada precisa ser configurado nos sites.

Se ele for mantido **privado**, cada site precisa de um token, no
`wp-config.php`:

```php
define( 'ENCONTRA_PUSH_GITHUB_TOKEN', 'github_pat_...' );
```

Use um *fine-grained token* com acesso somente de leitura a **este**
repositório. Vale pesar a troca: o token fica em texto no `wp-config.php` de
cada site, e um site comprometido entrega o acesso de leitura ao código. Sem
token, um repositório privado simplesmente não oferece atualização — o site
continua funcionando normalmente.

## Requisitos

WordPress 6.0+, PHP 8.1+, site em HTTPS com certificado válido.
