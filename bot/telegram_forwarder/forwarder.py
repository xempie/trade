# -*- coding: utf-8 -*-

from telethon import TelegramClient, events
import asyncio
import datetime
import traceback

api_id = 29462291     
api_hash = '991fb0f3a38ecd790cca6a14e7e2e59f'

source_channel = "-1002524727804"
target_channel = '@manualsignalcheck'

logfile = "/home/vahid279/public_html/addons/academit/bot/telegram_forwarder/log.txt"

def write_log(text):
    try:
        with open(logfile, "a") as f:
            f.write("{} - {}\n".format(datetime.datetime.now(), text))
    except Exception as e:
        # fallback silent fail
        pass

write_log("🚀 Script started!")

client = TelegramClient('session', api_id, api_hash)

async def main():
    try:
        write_log("🔌 Trying to connect to Telegram...")
        await client.connect()

        if not await client.is_user_authorized():
            write_log("❌ Session not authorized.")
            return

        write_log("✅ Connected and session authorized.")

        @client.on(events.NewMessage(chats=source_channel))
        async def handler(event):
            try:
                await client.forward_messages(target_channel, event.message)
                write_log("📨 Message forwarded: {}".format(event.message.id))
            except Exception as e:
                write_log("❌ Error forwarding message: {}".format(str(e)))

        write_log("📡 Listening for new messages...")
        await client.run_until_disconnected()

    except Exception as e:
        write_log("🔥 Exception in main: {}".format(str(e)))
        write_log(traceback.format_exc())

asyncio.get_event_loop().run_until_complete(main())
