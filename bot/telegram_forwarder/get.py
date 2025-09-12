from telethon import TelegramClient

api_id = 29462291       # جایگزین کن
api_hash = '991fb0f3a38ecd790cca6a14e7e2e59f'  # جایگزین کن

client = TelegramClient('session', api_id, api_hash)

async def main():
    dialogs = await client.get_dialogs()

    for dialog in dialogs:
        if dialog.is_channel and not dialog.is_group:
            print(f"Name: {dialog.name}")
            print(f"ID: {dialog.id}")
            print('-' * 30)

with client:
    client.loop.run_until_complete(main())