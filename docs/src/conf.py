# -*- coding: utf-8 -*-

import os
from subprocess import call, Popen, PIPE

git = Popen('which git 2> %s' % os.devnull, shell=True, stdout=PIPE
            ).stdout.read().strip()
cwd = os.getcwd()
root = os.path.abspath(os.path.join(cwd, '..', '..'))

buildenv = os.path.join(root, 'vendor', 'erebot', 'buildenv')
generic_doc = os.path.join(root, 'docs', 'src', 'generic')

origin = Popen([git, 'config', '--local', 'remote.origin.url'],
               stdout=PIPE).stdout.read().strip()
project = origin.rpartition('/')[2]
if project.endswith('.git'):
    project = project[:-4]

for repository, path in (
    ('git://github.com/Erebot/Erebot_Buildenv.git', buildenv),
    ('git://github.com/Erebot/Erebot_Module_Skeleton_Doc.git', generic_doc)
):
    if not os.path.isdir(path):
        os.makedirs(path)
        call([git, 'clone', repository, path])
    else:
        os.chdir(path)
        call([git, 'checkout', 'master'])
        call([git, 'pull'])
        os.chdir(cwd)

execfile(os.path.join(buildenv, 'sphinx', 'conf.py'))
