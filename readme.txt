=== Encontra Push ===
Contributors: encontra
Tags: web push, notificações, push notifications, autopush, service worker
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.17
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conecta este site ao painel Encontra Push: captura de assinantes, Service Worker, AutoPush por publicação, rastreio de clique e métricas no wp-admin.

== Description ==

O Encontra Push é a camada de integração entre um site WordPress e o painel
central em push.encontra.com.br. O plugin cuida do que só pode acontecer
dentro da origem do site — permissão do navegador, Service Worker, captura de
subscriptions e rastreio de clique — enquanto o painel cuida de campanhas,
segmentação, fila de envio e métricas de toda a rede.

= O que o plugin faz =

* Registra o Service Worker em `/encontra-push-sw.js`, com scope `/`.
* Exibe um pré-prompt próprio antes de chamar a permissão nativa.
* Captura e remove subscriptions, encaminhando ao painel de forma autenticada.
* Avisa o painel quando um post é publicado, para o AutoPush.
* Registra cliques vindos da notificação e limpa o parâmetro da URL.
* Mostra métricas do painel dentro do wp-admin, com cache de 5 minutos.
* Oferece um diagnóstico que aponta exatamente o que está faltando.

= O que o plugin não faz =

* Não guarda assinantes no banco do WordPress. A base vive no painel.
* Não usa WP-Cron para enviar campanhas. O processamento é do painel.
* Não substitui o Service Worker do site. Se houver outro no scope raiz, o
  plugin detecta, avisa e oferece os modos de integração B e C.
* Não substitui um manifest PWA já fornecido por outro plugin ou tema.

= Privacidade =

O plugin não grava nem envia endereço IP. Os dados enviados ao painel são os
necessários para entregar a notificação (endpoint e chaves da subscription,
tratados como credenciais técnicas) e para segmentar (navegador, sistema,
dispositivo, idioma, fuso, página de inscrição e UTM).

Quando o site está atrás da Cloudflare, o plugin também envia país, estado e
cidade aproximados, lidos dos cabeçalhos que a própria Cloudflare acrescenta
à requisição. Essa resolução acontece na borda dela, a partir do IP: o plugin
recebe apenas o resultado e nunca tem acesso ao endereço em si. A precisão é
de cidade, não de localização exata.

O consentimento do navegador não substitui as obrigações do controlador do
site perante a LGPD. Trate a política de privacidade do site como parte da
implantação, não como detalhe posterior.

== Installation ==

1. Envie a pasta `encontra-push` para `/wp-content/plugins/` ou instale o ZIP
   pelo painel do WordPress.
2. Ative o plugin.
3. No painel Encontra Push, cadastre o site e gere um código de conexão.
4. No WordPress, acesse **Encontra Push > Integração** e informe o código.
5. Clique em **Validar domínio**.
6. Abra **Encontra Push > Diagnóstico** e execute o teste de push.

O site precisa estar em HTTPS com certificado válido. Sem isso, o navegador
não registra o Service Worker e nada funciona.

== Frequently Asked Questions ==

= O site já tem um PWA. Vai quebrar? =

Não. O plugin detecta outro Service Worker controlando o scope raiz e não o
substitui. Nesse caso, configure o site no modo B (integração) no painel e
inclua o runtime fornecido dentro do Service Worker existente:

`importScripts('.../encontra-push/assets/js/sw-runtime.js');`

= Por que meu post publicado não gerou notificação? =

As causas mais comuns, em ordem:

1. Não existe regra de AutoPush ativa para o site no painel.
2. A regra tem filtro de categoria que exclui esse post.
3. A publicação caiu fora da janela de horário configurada.
4. O post foi apenas atualizado, e não publicado pela primeira vez.
5. O metabox do post está marcado como "Não enviar".

= Um assinante recebeu a mesma notificação duas vezes. Como evitar? =

O sistema usa idempotência por site, post e revisão de publicação, então um
webhook repetido não cria uma segunda campanha. Se a duplicata acontecer,
verifique se não existem duas regras de AutoPush cobrindo o mesmo conteúdo.

= As métricas de "recebido" estão mais baixas que as de "aceito". Está errado? =

Não. "Aceito" significa que o Push Service aceitou a solicitação. "Recebido" e
"exibido" dependem de o Service Worker executar e conseguir avisar de volta —
são métricas de melhor esforço. A única prova de interação é o clique.

= Posso apagar o plugin sem perder os assinantes? =

Sim. Por padrão, a desinstalação não remove nada. Em
**Encontra Push > Integração** há uma seção que define explicitamente o que
deve ser apagado ao remover o plugin.

== Changelog ==

= 1.0.17 =
* Novo: a tela Diagnóstico passa a mostrar a versão instalada, a versão
  publicada no repositório e um botão "Verificar atualização agora".
  A consulta fica em cache por 6 horas e o WordPress guarda a própria lista
  de atualizações — quando uma versão nova não aparecia, não havia como
  saber se o plugin ainda não tinha olhado ou se não havia nada novo.

= 1.0.15 =
* Corrigido: o link "Ativar atualizações automáticas" não aparecia para este
  plugin na tela de Plugins. O WordPress só oferece essa opção para plugin
  que ele conhece como "com atualização disponível" ou "em dia", e o plugin
  não se declarava em nenhum dos dois quando já estava na última versão.

= 1.0.14 =
* Novo: o plugin passa a se atualizar sozinho a partir do repositório no
  GitHub. A atualização aparece na tela de Plugins como a de qualquer outro
  plugin, com botão "Atualizar agora" — e funciona com a atualização
  automática do WordPress, se o site tiver ligado.
* Segurança: com o cabeçalho `Update URI` declarado, o WordPress deixa de
  consultar o wordpress.org para este plugin. Isso fecha a porta para alguém
  publicar um plugin com o mesmo slug no diretório oficial e ver os sites
  baixarem o pacote dele por cima deste.

= 1.0.13 =
* Corrigido: a notificação saía sem imagem mesmo quando o post tinha imagem
  destacada. O plugin lia a imagem cedo demais — no editor de blocos e na API
  REST, o WordPress grava a imagem destacada e as categorias DEPOIS do post.
  Em sites que publicam por automação isso valia para toda publicação.
  Agora a leitura acontece em `wp_after_insert_post`, que roda quando post,
  termos e metadados já estão salvos.
* Novo: filtro `encontra_push_post_image`, para temas que guardam a capa num
  campo próprio em vez da imagem destacada nativa.

= 1.0.12 =
* Alterado: as telas do plugin no wp-admin passam a usar acentuação correta.
  Os menus, títulos, rótulos, textos de ajuda e mensagens de erro estavam
  sem acento ("Solicitação", "Aparência", "Integração", "Diagnóstico").
  Nenhum endereço de tela mudou: quem tiver um link salvo continua chegando
  no mesmo lugar.

= 1.0.11 =
* Novo: a coluna Região do painel passa a ser preenchida. O plugin lê país,
  estado e cidade dos cabeçalhos que a Cloudflare já entrega e os manda junto
  com a inscrição. O endereço IP continua sem ser lido nem enviado.
  Para estado e cidade, ligue na Cloudflare do site:
  Rules > Managed Transforms > "Add visitor location headers".
  Sem isso, só o país é preenchido. Site que não usa Cloudflare segue sem
  essa informação, e nada mais muda.

= 1.0.10 =
* Corrigido: o card de destaque (a última notícia ao lado do sino) não
  aparecia. Um erro de JavaScript interrompia a montagem do sino logo no
  carregamento da página.

= 1.0.9 =
* Novo: o sino mostra as notícias recentes também para quem ainda NÃO ativou
  as notificações, com o convite logo abaixo da lista ("amostra").
* Novo: ao abrir o site, a última notícia aparece num card ao lado do sino e
  some sozinha depois de alguns segundos. Uma vez por visita.
* Alterado: o sino agora nasce do lado ESQUERDO e com 3 notícias. Sites que já
  salvaram a tela Sino de notícias mantêm o que escolheram.

= 1.0.8 =
* Novo: o evento de publicação passa a enviar também a versão média da
  imagem destacada, usada como miniatura ao lado do texto da notificação.
  A escolha de onde usar a imagem fica na regra de AutoPush, no painel
  (Template da notificação > Imagem destacada do post).

= 1.0.7 =
* Corrigido: o botão "Ativar notificações" do sino ficava parado em
  "Ativando..." quando o Chrome ou o Edge exibiam o pedido de permissão só
  como um ícone na barra de endereço. Agora o sino orienta a clicar nesse
  ícone e, em caso de falha, volta ao normal com o motivo.
* Corrigido: o Service Worker que não ativava deixava a inscrição esperando
  para sempre; agora há prazo de 15 segundos.
* Novo: o cache de página é limpo automaticamente quando o plugin é
  atualizado. Antes, o celular podia continuar recebendo a página antiga
  (o LiteSpeed guarda uma versão separada para celular).

= 1.0.6 =
* Novo: Sino de notícias, um botão fixo na lateral do site (direita ou
  esquerda) com contador de não lidas. Para quem está inscrito, mostra as
  últimas notícias publicadas, com marcação de lidas e o botão "Parar de
  receber". Para quem não está, mostra o convite para ativar as
  notificações. Configurável em Encontra Push > Sino de notícias.
* Corrigido: depois de desativar as notificações, a página seguinte
  reinscrevia o navegador sozinha, porque a permissão do navegador continuava
  concedida. A escolha do visitante agora é respeitada.

= 1.0.5 =
* Corrigido: o Tema escolhido em Aparência (Claro ou Escuro) era salvo mas
  ignorado; o pré-prompt seguia sempre o modo do sistema do visitante.
* Corrigido: a Cor do botão e o Arredondamento da borda também não eram
  aplicados ao pré-prompt.
* O aviso de instalação no iPhone passa a seguir o mesmo tema e as mesmas cores.

= 1.0.4 =
* Segurança: o clique na notificação só abre endereços http(s), nunca
  javascript:, data: ou file: (Service Worker dos modos A e B).
* Segurança: a rota pública /health deixou de expor a versão do WordPress e
  do PHP. O painel continua recebendo esses dados pelo heartbeat autenticado.
* Segurança: o endereço da API recebido pelo heartbeat só é aceito se estiver
  no mesmo domínio do painel (ex.: apipush.encontra.com.br). Outros hosts podem
  ser liberados pelo filtro encontra_push_trusted_api_hosts.
* Segurança: pareamento e chamadas ao painel exigem HTTPS, validam o
  certificado e não seguem redirecionamentos.
* Segurança: os avisos do wp-admin não levam mais o texto na URL (evita avisos
  falsos em links enviados a administradores).
* Corrigido: "Revogar conexão" na desinstalação não revogava nada (a chamada
  ia sem assinatura). Agora a credencial é revogada de fato no painel.
* Novo: a rota /verify responde com Cache-Control: no-store.

= 1.0.3 =
* Corrigido: no Chrome e no Edge, a primeira inscrição falhava em silêncio
  porque era pedida antes de o Service Worker ativar. Agora aguarda a ativacao.
* Corrigido: uma inscrição feita com outra chave VAPID (chave rotacionada ou
  herdada de provedor anterior) era reaproveitada. Agora é recriada.
* Corrigido: a resposta do site ao registrar a inscrição era ignorada.
* Novo: cada motivo de falha na inscrição deixa uma linha no console do
  navegador, com o prefixo "[Encontra Push]".
* Novo: o cache de página (LiteSpeed Cache, WP Rocket, W3 Total Cache,
  WP Super Cache, WP Fastest Cache, Autoptimize) é limpo automaticamente ao
  conectar, desconectar ou alterar pré-prompt e aparência.
* Novo: o arquivo do Service Worker é marcado como não cacheável para o
  LiteSpeed.

= 1.0.2 =
* Novo: user-agent próprio (EncontraPush/versão) também no pareamento e na
  validação, que antes usavam o padrão do WordPress.
* Novo: quando o servidor do painel recusa a requisição antes de ela chegar ao
  Encontra Push (firewall, proxy), a mensagem diz isso e mostra o servidor e o
  início da resposta, em vez de apenas o código HTTP.

= 1.0.1 =
* Novo: endereço da API separado do endereço do painel. O painel pode mover a
  API para outro host (ex.: apipush.encontra.com.br) e o plugin acompanha
  sozinho pelo heartbeat, sem atualização nem reconexao.

= 1.0.0 =
* Versão inicial: pairing seguro, Service Worker, pré-prompt configurável,
  AutoPush por publicação, rastreio de clique, diagnóstico e métricas.

== Upgrade Notice ==

= 1.0.17 =
Mostra o estado da atualizacao e um botao para verificar na hora.

= 1.0.15 =
Libera o "Ativar atualizacoes automaticas" na tela de Plugins.

= 1.0.14 =
Depois desta, as proximas atualizacoes aparecem sozinhas na tela de Plugins.

= 1.0.13 =
Corrige a notificação sair sem a imagem destacada do post. Recomendado.

= 1.0.12 =
Acentuação correta nas telas do plugin. Nenhum endereço de tela mudou.

= 1.0.11 =
Preenche país, estado e cidade do assinante pela Cloudflare, sem usar o IP.

= 1.0.10 =
Correcao: o card de destaque ao lado do sino não aparecia. Atualize.

= 1.0.9 =
Sino à esquerda, amostra de notícias para não inscritos e card de destaque.

= 1.0.8 =
Necessário para a notificação sair com a miniatura da imagem destacada.

= 1.0.7 =
Corrige o sino parado em "Ativando..." e limpa o cache ao atualizar.

= 1.0.6 =
Novo Sino de notícias, ligado por padrão. Revise em Encontra Push > Sino de notícias.

= 1.0.5 =
O pré-prompt passa a respeitar Tema, Cor do botão e Arredondamento da tela Aparência.

= 1.0.4 =
Correcoes de segurança da auditoria de 18/09/2026. Atualize todos os sites.
Requer o painel atualizado para a revogação na desinstalação.

= 1.0.3 =
Corrige a inscrição de visitantes no Chrome e no Edge. Atualize e limpe o cache
de página uma vez após o envio.

= 1.0.0 =
Primeira versão pública.
