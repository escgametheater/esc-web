import React, { PureComponent, Fragment } from "react";

import audioTrack from "./10-minutes-of-silence.mp3";

import { ReactComponent as PlayIcon } from "../icons/ic_play.svg";
import { ReactComponent as PauseIcon } from "../icons/ic_pause.svg";
import { ReactComponent as ChevronLeft } from "../icons/ic_chevron_left.svg";
import { ReactComponent as ChevronRight } from "../icons/ic_chevron_right.svg";

import "./VTTPlayer.css";

class VTTPlayer extends PureComponent {
  constructor(props) {
    super(props);
    this.state = {
      playing: true,
      cues: [],
      currentCueIndex: 0,
    };
  }
  _memoizeVideo = el => {
    const player = this._videoEl = el;

    if (!el) {
      return;
    }

    const { onCueEnter, onCueExit, onCuesLoaded, loop } = this.props;

    el.addEventListener("canplay", () => {
      let cues = Array.prototype.slice.call(el.textTracks[0].cues);
      cues = cues.map(c => {
        try {
          c.data = JSON.parse(c.text);
        } catch (e) {
          console.log("unable to parse" , c.text)
        }
        return c;
      });

      this.setState({
        cues,
      })

      cues.forEach((cue, i) => {
        cue.onenter = () => {
          if (onCueEnter) {
            onCueEnter(JSON.parse(cue.text), i);
          }

          this.setState({
            currentCueIndex: i,
          });
        }

        if (onCueExit || loop) {
          cue.onexit = () => {
            if (onCueExit) {
              onCueExit(JSON.parse(cue.text));
            }

            if (loop && i === cues.length - 1) {
              el.currentTime = 0;
              console.log("VTTPlayer:: restarting");
            }
          }
        }
      });
      if (onCuesLoaded) {
        onCuesLoaded(cues, player);
      }
    });
  };

  handleTogglePlay = () => {
    const { playing } = this.state;
    let nextPlaying;

    if (!playing) {
      this._videoEl.play();
      nextPlaying = true;
    } else {
      this._videoEl.pause();
      nextPlaying = false;      
    }

    this.setState({
      playing: nextPlaying,
    })
  };

  handleSkipTo = (time, currentCueIndex) => {
    if (!this._videoEl) {
      return;
    }

    // Temporarily play to skip to a point
    if (!this.state.playing) {
      this._videoEl.play().then(() => {
        console.log("Play then skip to time ... ", time, currentCueIndex);
        this._videoEl.currentTime = parseFloat((time + 0.001).toPrecision(4));
        this._videoEl.pause();
        this.setState({
          currentCueIndex,
        });

        // if (this.props.onCueEnter) {
        //   this.props.onCueEnter(JSON.parse(cue.text), currentCueIndex);
        // }
      });
    } else {
      this._videoEl.currentTime = time;
    }
  }

  handleSkipToNext = () => {
    const {cues, currentCueIndex} = this.state;

    this.handleSkipTo(cues[currentCueIndex+1].startTime, currentCueIndex+1);
  }

  handleSkipToPrevious = () => {
    const {cues, currentCueIndex} = this.state;

    console.log("##", cues[currentCueIndex-1], cues[currentCueIndex-1].startTime);

    this.handleSkipTo(cues[currentCueIndex-1].startTime, currentCueIndex-1);
  }

  render() {
    return (
      <div className="vtt-player">
        <button disabled={this.state.currentCueIndex === 0} onClick={this.handleSkipToPrevious}>
          <ChevronLeft />
        </button>
        <button
          style={{
            border: "2px solid #CED4D9",
          }}
          onClick={this.handleTogglePlay}
        >
        {
          this.state.playing ? (<PauseIcon />) : (<PlayIcon />)
        }
        </button>
        <button disabled={this.state.currentCueIndex === this.state.cues.length - 1} onClick={this.handleSkipToNext}>
          <ChevronRight />
        </button>
        {this.props.vttData && (
          <video width="0" height="0" ref={this._memoizeVideo} muted={true}>
            <source type="audio/mp3" src={audioTrack} />
            <track
              label="English"
              kind="subtitles"
              srcLang="en"
              src={this.props.vttData}
              default
            />
          </video>
        )}
      </div>
    );
  }
}

export default VTTPlayer;
