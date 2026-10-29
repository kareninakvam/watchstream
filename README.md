# Watchstream

Plataforma web para comunidades de streaming: participantes entram com a conta do provedor, participam do chat de uma live, acumulam pontos e trocam esses pontos por recompensas.

A elegibilidade será baseada na participação no chat; não representa comprovação de tempo assistido. Os pontos são inteiros e não têm valor monetário.

## Estado do projeto

Em desenvolvimento. A fundação técnica e o modelo inicial de dados estão implementados e validados localmente:

- Ambiente Docker com processos separados para aplicação, filas, agendamento, ingestão e coleta de métricas.
- Modelo de usuários, identidades externas, canais, lives, eventos, carteiras, extrato, recompensas e resgates.
- Identidades e origens de operações com restrições de unicidade; chaves estrangeiras e índices de consulta.
- Tokens criptografados, datas de negócio em UTC, proteção administrativa e filtragem de segredos nos logs.
- Testes automatizados com MySQL e Redis isolados.

Login pelo provedor, conexão real com o chat, concessão de pontos, loja e fluxo de resgates ainda serão implementados. O ingestor atual permanece em espera e informa explicitamente esse estado nos logs.

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.4 e Laravel 13 |
| Interface | Blade, Tailwind CSS 4 e Vite |
| Banco de dados | MySQL 8.4 LTS |
| Filas, cache e sessões | Redis 7.4 |
| Processamento de filas | Laravel Horizon |
| Métricas da aplicação | Laravel Pulse |
| Assets | Node.js 24 LTS e npm |
| Ambiente local | Docker Compose com containers Linux |
| Testes | PHPUnit 12 e Laravel Testing |

As imagens externas estão fixadas por digest. `composer.lock` e `package-lock.json` registram as versões das dependências. Pacotes do sistema operacional ainda dependem dos repositórios Debian disponíveis durante o build.

## Arquitetura

O mesmo código Laravel e a mesma imagem PHP são executados em cinco processos independentes:

| Serviço | Responsabilidade | Comando |
|---|---|---|
| `web` | Requisições HTTP | `php artisan serve --host=0.0.0.0 --port=8000` |
| `worker` | Processamento das filas | `php artisan horizon` |
| `scheduler` | Agendamento | `php artisan schedule:work` |
| `ingestor` | Processo dedicado de chat, provisório | `php artisan chat:consume` |
| `pulse` | Coleta de métricas | `php artisan pulse:check` |
| `mysql` | Persistência durável | MySQL |
| `redis` | Filas, cache e sessões | Redis |

MySQL é a fonte durável dos eventos, saldos e movimentações. Redis apoia o processamento, sem substituir os registros de negócio no banco. As migrations são executadas em uma etapa explícita do setup, não no entrypoint de cada processo.

As filas `chat`, `points` e `redemptions` estão configuradas; resgates têm processo reservado. As regras e os jobs de negócio serão adicionados nas etapas seguintes.

## Modelo de dados

| Tabela | Finalidade |
|---|---|
| `users` | Contas locais e permissão administrativa |
| `external_identities` | Identidades do provedor e tokens criptografados |
| `channels` | Canal e autorização de integração |
| `live_sessions` | Sessões de transmissão e suas datas |
| `received_events` | Eventos recebidos e estado de processamento |
| `wallets` | Uma carteira por usuário |
| `ledger_entries` | Créditos, débitos e estornos com origem identificável |
| `rewards` | Catálogo, preço, estoque e disponibilidade |
| `redemptions` | Resgates, snapshots e resolução administrativa |

Carteiras não aceitam saldo negativo; recompensas exigem preço positivo e estoque não negativo. Identidades externas são únicas por provedor e identificador estável. Exclusões que destruiriam registros relacionados são bloqueadas por chaves estrangeiras. A operação transacional de créditos e resgates será implementada junto aos respectivos fluxos.

## Requisitos locais

- Git.
- Docker Desktop com virtualização habilitada e motor de containers Linux em execução, ou Docker Engine com Docker Compose.
- PowerShell para utilizar os scripts `.ps1` fornecidos.

PHP, Composer, MySQL, Redis e Node não precisam ser instalados no computador: são executados em containers. Os comandos abaixo partem da pasta do repositório.

## Instalação

Clone o repositório e prepare a configuração:

```powershell
git clone https://github.com/kareninakvam/watchstream.git
cd watchstream
```

```powershell
Copy-Item .env.example .env
```

Revise as credenciais locais de exemplo no `.env` antes de iniciar. Elas são fictícias e não devem ser utilizadas em servidores públicos. Não versione esse arquivo.

Execute:

```powershell
.\scripts\setup.ps1
```

O setup constrói a imagem, aguarda MySQL e Redis, instala as dependências pelos lockfiles, gera `APP_KEY` se estiver vazia, executa migrations e seeds, compila os assets e inicia os serviços. Um `.env` existente é preservado. O seeder atual não cria usuários, administradores ou dados fictícios de negócio.

No Windows, se a política de execução bloquear um script local, permita sua execução apenas na sessão atual:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned
```

Aplicação: **http://localhost:8080**. Verificação de saúde: **http://localhost:8080/up**.

Somente `127.0.0.1:8080` é publicada por padrão. MySQL e Redis ficam disponíveis apenas na rede Docker. As rotas `/admin`, `/horizon` e `/pulse` são restritas a administradores, inclusive em desenvolvimento; não há login ou conta administrativa de demonstração nesta etapa.

## Uso diário

```powershell
docker compose up -d --wait
docker compose ps
docker compose logs --tail 100 web worker scheduler ingestor pulse
docker compose stop
```

Para remover os containers mantendo os dados:

```powershell
docker compose down
```

**`docker compose down -v` remove os volumes e seus dados.**

Para aplicar novas migrations:

```powershell
docker compose exec web php artisan migrate --no-interaction
```

## Desenvolvimento da interface

Depois do setup, ative o servidor Vite com atualização automática:

```powershell
docker compose --profile frontend up -d vite
```

Esse perfil publica também `127.0.0.1:5173`. Para voltar aos assets compilados:

```powershell
docker compose --profile frontend stop vite
docker compose --profile tools run --rm node npm run build
Remove-Item -LiteralPath public/hot -ErrorAction SilentlyContinue
```

## Testes e qualidade

O desenvolvimento segue TDD quando aplicável: teste com falha significativa, implementação e refatoração com testes passando.

Execute a suíte completa:

```powershell
.\scripts\test.ps1
```

Ou filtre um conjunto:

```powershell
.\scripts\test.ps1 -Filter DomainFoundationTest
.\scripts\test.ps1 -Filter AdministrativeAccessTest
```

O runner usa `compose.tests.yaml` e `phpunit.mysql.xml`, com MySQL/Redis exclusivos do projeto `watchstream-tests`, sem portas públicas ou volumes do desenvolvimento. O banco de testes é temporário. Os containers de teste são removidos ao terminar, inclusive se houver falha.

Não execute duas instâncias desse runner simultaneamente. Os testes de integração recusam outro host, banco ou ambiente antes de executar migrations. Nunca aponte essa suíte para o banco local ou de produção.

A suíte atual cobre integridade do domínio, identidade externa, criptografia, UTC, acesso administrativo e proteção de logs. `docker compose exec web php artisan test` executa apenas as suítes leves Unit/Feature; não substitui o runner completo.

Para formatar o código PHP:

```powershell
docker compose exec web vendor/bin/pint
```

## Estrutura

```text
app/                 Modelos, casts, middleware, logs e comandos
bootstrap/           Inicialização da aplicação
config/              Configurações Laravel e dos serviços
database/            Migrations, factories e seeders
docker/              Entrypoint e configuração PHP
public/              Entrada HTTP e assets públicos
resources/           Views Blade, CSS e JavaScript
routes/              Rotas HTTP e comandos
scripts/             Setup e execução dos testes
tests/               Testes unitários, funcionais e de integração
compose.yaml         Ambiente de desenvolvimento
compose.tests.yaml   Ambiente isolado de testes
Dockerfile           Imagem PHP compartilhada
```

## Configuração e segurança

- `.env.example` contém apenas configurações e credenciais fictícias. Credenciais reais são configuradas fora do Git.
- Preserve `APP_KEY`: ela é necessária para descriptografar os tokens armazenados. A troca da chave exige uma estratégia de rotação.
- Os tokens são criptografados e ocultos na serialização dos modelos.
- Os logs filtram campos sensíveis e padrões conhecidos. Isso não torna seguro registrar payloads ou segredos arbitrários.
- Datas de negócio são armazenadas em UTC. `APP_DISPLAY_TIMEZONE` define o fuso previsto para apresentação.
- Instalações existentes podem ter variáveis de compatibilidade para volumes e namespaces. Preserve-as para manter o acesso aos dados anteriores.

## Homologação e produção

O Dockerfile e o Compose atuais atendem ao desenvolvimento local, com bind mounts e servidor Artisan. A publicação no Railway ainda requer imagem de release, servidor HTTP adequado, processos separados, credenciais próprias, etapa única de migrations, pipeline e estratégia de recuperação.

O funcionamento local e os testes não representam uma implantação em produção.
