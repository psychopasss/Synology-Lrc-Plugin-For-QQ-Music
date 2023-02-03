# Synology Lrc Plugin For QQ Music

Lyrics plugin for Synology Audio Station/DS Audio.

用于群晖 Audio Station/DS Audio 的歌词插件。  

version: 1.2.3

- [x] 显示歌词翻译
- [x] 去除英文歌词乱码
- [x] 英文歌词与中文歌词，换行展示

因为网易版权太少了，所以改版为QQ音乐，搜索歌词，只是用来学术研究，禁止用于商业用途。

### FAQ:
#### 关于歌词文件是否可以下载到本地文件保存的问题解答
<img width="623" alt="image" src="https://user-images.githubusercontent.com/11738089/169771351-3c517ab6-3958-46c2-a72f-b18969c4096e.png">
经过调研，群晖系统为了安全，不允许插件进行文件操作，实现不了自动下载。

<img width="591" alt="image" src="https://user-images.githubusercontent.com/11738089/169772097-34d1adbf-53ab-4c3b-b5b3-d0935012904a.png">

只能在歌曲信息中，歌词tab手动下载到本地（注意mp3格式的歌曲，歌词会保存到歌曲本身标签信息中，不会另存为lrc，flac歌曲，会另存为lrc）。

[点击查看，Synology官方文档](https://global.download.synology.com/download/Document/Software/DeveloperGuide/Package/AudioStation/All/enu/AS_Guide.pdf)

### Refer:
改版自：<https://github.com/LudySu/Synology-LrcPlugin> 感谢 @LudySu 的肩膀。

## Contributors

<!-- readme: collaborators,contributors -start -->
<table>
<tr>
    <td align="center">
        <a href="https://github.com/psychopasss">
            <img src="https://avatars.githubusercontent.com/u/11738089?v=4" width="100;" alt="psychopasss"/>
            <br />
            <sub><b>Psycho Pass</b></sub>
        </a>
    </td>
    <td align="center">
        <a href="https://github.com/cospotato">
            <img src="https://avatars.githubusercontent.com/u/7987906?v=4" width="100;" alt="cospotato"/>
            <br />
            <sub><b>CosPotato</b></sub>
        </a>
    </td></tr>
</table>
<!-- readme: collaborators,contributors -end -->
