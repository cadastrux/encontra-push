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

## Requisitos

WordPress 6.0+, PHP 8.1+, site em HTTPS com certificado válido.
