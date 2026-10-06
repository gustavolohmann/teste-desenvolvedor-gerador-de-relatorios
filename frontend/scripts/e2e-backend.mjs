import { spawn } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const backend = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../backend')
const database = path.join(backend, 'database', 'playwright.sqlite')
const env = {
  ...process.env,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: database,
  DB_URL: '',
  FRONTEND_URL: 'http://127.0.0.1:5174',
}

function php(args) {
  return spawn('php', args, { cwd: backend, env, stdio: 'inherit' })
}

const migrate = php(['artisan', 'migrate:fresh', '--seed', '--force'])
const migrated = await new Promise((resolve) => {
  migrate.on('exit', resolve)
})

if (migrated !== 0) {
  process.exit(migrated ?? 1)
}

const server = php(['artisan', 'serve', '--host=127.0.0.1', '--port=8123'])

function stop() {
  server.kill()
}

process.on('SIGTERM', stop)
process.on('SIGINT', stop)
server.on('exit', (status) => {
  process.exit(status ?? 0)
})
